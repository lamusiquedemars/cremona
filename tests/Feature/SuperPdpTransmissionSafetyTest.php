<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\PrivateDocument;
use App\Services\En16931InvoiceFactory;
use App\Services\InvoiceElectronicDocumentManager;
use App\Services\SuperPdpClient;
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
            $this->mock(SuperPdpClient::class)->shouldReceive('submitCii')->once()->andReturn(['id' => 123]);

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
