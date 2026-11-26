<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\PrivateDocument;
use App\Services\ElectronicInvoiceFinalizationManager;
use App\Services\ElectronicInvoiceFlow;
use App\Services\En16931InvoiceFactory;
use App\Services\InvoiceElectronicDocumentManager;
use App\Services\SuperPdpClient;
use App\Services\SuperPdpConnectionResolver;
use App\Services\SuperPdpTransmissionManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class SuperPdpTransmissionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_submission_keeps_the_remote_identifier(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $invoice = $this->invoiceWithIntegration();
            $transmission = $invoice->transmissions()->firstOrFail();
            $document = $this->validatedDocument($invoice);
            $transmission->update(['status' => 'validated', 'electronic_private_document_id' => $document->id]);
            $this->mock(SuperPdpClient::class)->shouldReceive('submitCii')->once()
                ->withArgs(fn (string $id, string $secret, string $content, string $externalId, string $rule): bool => $rule === 'B2C')
                ->andReturn(['id' => 123]);

            $result = app(SuperPdpTransmissionManager::class)->submit($invoice);

            $this->assertSame('submitted', $result->status);
            $this->assertSame(123, $result->metadata['super_pdp_invoice_id']);
            $this->assertNotNull($result->submitted_at);
        });
    }

    public function test_unknown_submission_is_recorded_and_cannot_be_retried(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $invoice = $this->invoiceWithIntegration();
            $transmission = $invoice->transmissions()->firstOrFail();
            $document = $this->validatedDocument($invoice);
            $transmission->update(['status' => 'validated', 'electronic_private_document_id' => $document->id]);

            $this->mock(SuperPdpClient::class)->shouldReceive('submitCii')->once()->andThrow(new RuntimeException('timeout'));
            try {
                app(SuperPdpTransmissionManager::class)->submit($invoice);
                $this->fail('Une erreur réseau doit être signalée.');
            } catch (RuntimeException $exception) {
                $this->assertSame('timeout', $exception->getMessage());
            }

            $transmission->refresh();
            $this->assertSame('submission_unknown', $transmission->status);
            $this->assertSame('crm-'.$invoice->public_id, $transmission->external_id);
            $this->expectException(LogicException::class);
            app(SuperPdpTransmissionManager::class)->submit($invoice);
        });
    }

    public function test_revalidation_preserves_existing_files_and_is_blocked_after_submission(): void
    {
        Storage::fake('local');
        config(['private-documents.disk' => 'local']);
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $invoice = $this->invoiceWithIntegration();
            $this->mock(En16931InvoiceFactory::class)->shouldReceive('make')->twice()->andReturn([]);
            $client = $this->mock(SuperPdpClient::class);
            $client->shouldReceive('convertEn16931ToCii')->twice()->andReturn('<first/>', '<second/>');
            $client->shouldReceive('validateCii')->twice()->andReturn(['data' => [['is_valid' => true]]]);

            $manager = app(InvoiceElectronicDocumentManager::class);
            $first = $manager->generateAndValidate($invoice)['document'];
            $second = $manager->generateAndValidate($invoice)['document'];
            $this->assertNotSame($first->path, $second->path);
            $this->assertSame('<first/>', Storage::disk('local')->get($first->path));
            $this->assertSame('<second/>', Storage::disk('local')->get($second->path));

            $transmission = $invoice->transmissions()->firstOrFail();
            $transmission->update(['status' => 'submitted']);
            try {
                $manager->generateAndValidate($invoice);
                $this->fail('Une facture transmise ne doit pas être revalidée.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('ne peut plus être validé', $exception->getMessage());
            }
            $this->assertSame('submitted', $transmission->fresh()->status);
            Storage::disk('local')->assertExists($first->path);
            Storage::disk('local')->assertExists($second->path);
        });
    }

    public function test_finalization_performs_validation_and_submission_in_one_business_action(): void
    {
        Storage::fake('local');
        config(['private-documents.disk' => 'local']);
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $invoice = $this->invoiceWithIntegration();
            $invoice->forceFill(['status' => 'issued'])->saveQuietly();
            $this->mock(En16931InvoiceFactory::class)->shouldReceive('make')->once()->andReturn([]);
            $client = $this->mock(SuperPdpClient::class);
            $client->shouldReceive('convertEn16931ToCii')->once()->andReturn('<invoice/>');
            $client->shouldReceive('validateCii')->once()->andReturn(['data' => [['is_valid' => true]]]);
            $client->shouldReceive('submitCii')->once()->andReturn(['id' => 123]);

            $result = app(ElectronicInvoiceFinalizationManager::class)->finalizeAndTransmit($invoice);

            $this->assertTrue($result['submitted']);
            $this->assertTrue($result['b2c']);
            $this->assertSame('submitted', $result['transmission']->status);
            $this->assertStringContainsString('e-reporting', $result['transmission']->metadata['message']);
            $this->assertSame('Finaliser la facture', app(ElectronicInvoiceFlow::class)->actionLabel($invoice->fresh()));
        });
    }

    public function test_a_validation_failure_is_explained_without_reissuing_the_invoice(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $invoice = $this->invoiceWithIntegration();
            $invoice->forceFill(['status' => 'issued', 'issued_at' => now()])->saveQuietly();
            $this->mock(En16931InvoiceFactory::class)->shouldReceive('make')->once()->andReturn([]);
            $this->mock(SuperPdpClient::class)->shouldReceive('convertEn16931ToCii')->once()->andThrow(new RuntimeException('timeout'));

            try {
                app(ElectronicInvoiceFinalizationManager::class)->finalizeAndTransmit($invoice);
                $this->fail('Une indisponibilité de plateforme doit être signalée.');
            } catch (RuntimeException $exception) {
                $this->assertSame('timeout', $exception->getMessage());
            }

            $transmission = $invoice->transmissions()->firstOrFail()->fresh();
            $this->assertSame('validation_failed', $transmission->status);
            $this->assertStringContainsString('numéro sont conservés', $transmission->last_error);
            $this->assertSame('issued', $invoice->fresh()->status->value);
        });
    }

    public function test_business_invoice_is_routed_as_b2b(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationIntegration::query()->create([
                'provider' => 'super_pdp', 'name' => 'production', 'status' => 'active',
                'credentials' => ['client_id' => 'test', 'client_secret' => 'test'],
            ]);
            $company = Company::query()->create([
                'name' => 'Client professionnel', 'country_code' => 'FR', 'registration_number' => '123456789',
            ]);
            $invoice = Invoice::query()->create(['title' => 'Facture B2B', 'company_id' => $company->id]);
            $invoice->forceFill(['status' => 'issued', 'recipient_snapshot' => ['country_code' => 'FR', 'registration_number' => '123456789']])->saveQuietly();
            $invoice->transmissions()->create(['provider' => 'super_pdp', 'status' => 'validated']);
            $document = $this->validatedDocument($invoice);
            $invoice->transmissions()->firstOrFail()->update(['electronic_private_document_id' => $document->id]);
            $this->mock(SuperPdpClient::class)->shouldReceive('submitCii')->once()
                ->withArgs(fn (string $id, string $secret, string $content, string $externalId, string $rule): bool => $rule === 'B2B')
                ->andReturn(['id' => 456]);

            $transmission = app(SuperPdpTransmissionManager::class)->submit($invoice);

            $this->assertStringContainsString('routage', $transmission->metadata['message']);
        });
    }

    public function test_sandbox_connection_is_used_only_in_the_test_environment(): void
    {
        $organization = Organization::factory()->create();
        config(['services.super_pdp.sandbox_client_id' => 'sandbox-id', 'services.super_pdp.sandbox_client_secret' => 'sandbox-secret']);
        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationIntegration::query()->create([
                'provider' => 'super_pdp', 'name' => 'sandbox', 'status' => 'active', 'credentials' => [],
            ]);
            $invoice = Invoice::query()->create(['title' => 'Facture sandbox']);

            $this->assertSame([
                'client_id' => 'sandbox-id',
                'client_secret' => 'sandbox-secret',
            ], app(SuperPdpConnectionResolver::class)->credentials($invoice));
        });
    }

    public function test_scheduled_synchronization_updates_submitted_invoices(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $invoice = $this->invoiceWithIntegration();
            $invoice->transmissions()->firstOrFail()->update([
                'direction' => 'outbound', 'status' => 'submitted', 'metadata' => ['super_pdp_invoice_id' => 123],
            ]);
            $this->mock(SuperPdpClient::class)->shouldReceive('invoiceEvents')->once()->andReturn([
                'data' => [['id' => 1, 'status_code' => 'api:sent']], 'has_after' => false,
            ]);

            $this->artisan('cremona:sync-electronic-invoice-statuses')->assertExitCode(0);

            $this->assertSame('sent', $invoice->transmissions()->firstOrFail()->fresh()->status);
        });
    }

    private function invoiceWithIntegration(): Invoice
    {
        OrganizationIntegration::query()->create([
            'provider' => 'super_pdp', 'name' => 'production', 'status' => 'active',
            'credentials' => ['client_id' => 'test', 'client_secret' => 'test'],
        ]);
        $invoice = Invoice::query()->create(['title' => 'Facture de test']);
        $invoice->transmissions()->create(['provider' => 'super_pdp', 'status' => 'ready_to_transmit']);

        return $invoice;
    }

    private function validatedDocument(Invoice $invoice): PrivateDocument
    {
        Storage::disk('local')->put('test.xml', '<invoice/>');

        return PrivateDocument::query()->create([
            'title' => 'Facture', 'category' => 'accounting_record', 'disk' => 'local', 'path' => 'test.xml',
            'original_name' => 'test.xml', 'size' => 10, 'sha256' => hash('sha256', '<invoice/>'),
        ]);
    }
}
