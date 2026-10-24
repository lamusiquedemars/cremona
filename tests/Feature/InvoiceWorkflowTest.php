<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\OrganizationInvoiceSettings;
use App\Models\OrganizationLegalProfile;
use App\Models\Person;
use App\Models\User;
use App\Services\InvoiceWorkflowManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_is_numbered_snapshotted_and_immutable_when_issued(): void
    {
        config(['private-documents.disk' => 'local']);
        Storage::fake('local');
        $organization = Organization::factory()->create(['name' => 'Contempo']);

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->legalProfile();
            OrganizationInvoiceSettings::query()->create([
                'reference_prefix' => 'CONT', 'next_reference_number' => 42, 'default_due_days' => 30,
                'default_tax_rate' => 20, 'super_pdp_enabled' => false,
            ]);
            $person = Person::query()->create(['display_name' => 'Camille Martin', 'address_line_1' => '2 rue du Client', 'postal_code' => '69001', 'city' => 'Lyon', 'country_code' => 'FR']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $invoice = Invoice::query()->create(['title' => 'Réparation violoncelle', 'person_id' => $person->id]);
            $invoice->lines()->create(['position' => 1, 'description' => 'Réglage', 'quantity' => 2, 'unit_amount' => 50, 'tax_rate' => 20]);

            $issued = app(InvoiceWorkflowManager::class)->issue($invoice);

            $this->assertSame(InvoiceStatus::Issued, $issued->status);
            $this->assertSame('CONT-'.today()->format('Y').'-00042', $issued->reference);
            $this->assertSame('Contempo', $issued->issuer_snapshot['legal_name']);
            $this->assertSame('Camille Martin', $issued->recipient_snapshot['name']);
            $this->assertSame('100.00', $issued->subtotal_amount);
            $this->assertSame('20.00', $issued->tax_amount);
            $this->assertSame('120.00', $issued->total_amount);
            $this->assertSame('120.00', $issued->balance_amount);
            $this->assertNotNull($issued->pdfPrivateDocument);
            Storage::disk('local')->assertExists($issued->pdfPrivateDocument->path);
            $this->assertCount(1, $issued->transmissions);
            $this->assertSame('super_pdp', $issued->transmissions->first()->provider);
            $this->assertSame('awaiting_configuration', $issued->transmissions->first()->status);
            $this->assertSame(43, OrganizationInvoiceSettings::query()->firstOrFail()->next_reference_number);

            $issued->title = 'Modification interdite';
            try {
                $issued->save();
                $this->fail('Une facture émise ne peut pas être modifiée.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('ne peut pas être modifiée', $exception->getMessage());
            }
        });
    }

    public function test_a_payment_updates_the_balance_without_modifying_the_issued_invoice(): void
    {
        config(['private-documents.disk' => 'local']);
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        app(OrganizationContext::class)->run($organization, function () use ($user): void {
            $this->legalProfile();
            $person = Person::query()->create(['display_name' => 'Camille Martin']);
            $invoice = Invoice::query()->create(['title' => 'Location novembre', 'person_id' => $person->id]);
            $invoice->lines()->create(['position' => 1, 'description' => 'Location', 'quantity' => 1, 'unit_amount' => 30, 'tax_rate' => 0]);
            $invoice = app(InvoiceWorkflowManager::class)->issue($invoice);

            InvoicePayment::query()->create(['invoice_id' => $invoice->id, 'recorded_by_user_id' => $user->id, 'paid_on' => today(), 'amount' => 10, 'method' => 'bank_transfer']);
            $invoice->refresh();
            $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
            $this->assertSame('20.00', $invoice->balance_amount);

            InvoicePayment::query()->create(['invoice_id' => $invoice->id, 'recorded_by_user_id' => $user->id, 'paid_on' => today(), 'amount' => 20, 'method' => 'bank_transfer']);
            $invoice->refresh();
            $this->assertSame(InvoiceStatus::Paid, $invoice->status);
            $this->assertSame('0.00', $invoice->balance_amount);
        });
    }

    private function legalProfile(): void
    {
        OrganizationLegalProfile::query()->create([
            'display_name' => 'Contempo', 'legal_name' => 'Contempo', 'email' => 'bonjour@contempo.test',
            'address_line_1' => '1 rue des Cordes', 'postal_code' => '69001', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '12345678900012',
        ]);
    }
}
