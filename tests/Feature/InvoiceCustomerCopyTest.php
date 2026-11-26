<?php

namespace Tests\Feature;

use App\Mail\InvoiceCustomerCopySent;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PrivateDocument;
use App\Services\InvoiceCustomerCopyManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class InvoiceCustomerCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_b2c_invoice_pdf_can_be_sent_to_a_manual_email_and_is_traced(): void
    {
        Mail::fake();
        Storage::fake('local');
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $invoice = $this->issuedInvoice();
            $delivery = app(InvoiceCustomerCopyManager::class)->sendByEmail($invoice, 'camille@example.test');

            $this->assertSame('camille@example.test', $delivery->recipient_email);
            $this->assertNotNull($delivery->sent_at);
            $this->assertSame($invoice->id, $delivery->invoice_id);
            Mail::assertSent(InvoiceCustomerCopySent::class, fn (InvoiceCustomerCopySent $mail): bool => $mail->hasTo('camille@example.test') && $mail->invoice->is($invoice));
        });
    }

    public function test_a_b2b_invoice_cannot_be_emailed_as_the_customer_delivery_channel(): void
    {
        Mail::fake();
        Storage::fake('local');
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $company = Company::query()->create(['name' => 'Client professionnel', 'country_code' => 'FR', 'registration_number' => '123456789']);
            $invoice = $this->issuedInvoice(['company_id' => $company->id, 'recipient_snapshot' => ['name' => 'Client professionnel', 'country_code' => 'FR']]);

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('réservée aux factures destinées à un particulier');
            app(InvoiceCustomerCopyManager::class)->sendByEmail($invoice, 'client@example.test');
        });
    }

    /** @param array<string, mixed> $attributes */
    private function issuedInvoice(array $attributes = []): Invoice
    {
        Storage::disk('local')->put('test-invoice.pdf', '%PDF-test');
        $document = PrivateDocument::query()->create([
            'title' => 'Facture FAC-2026-00001', 'category' => 'accounting_record', 'disk' => 'local', 'path' => 'test-invoice.pdf',
            'original_name' => 'Facture-FAC-2026-00001.pdf', 'declared_mime_type' => 'application/pdf', 'detected_mime_type' => 'application/pdf',
            'size' => 9, 'sha256' => hash('sha256', '%PDF-test'),
        ]);
        $invoice = Invoice::query()->create(array_merge(['title' => 'Révision', 'reference' => 'FAC-2026-00001'], $attributes));
        $invoice->forceFill(array_merge([
            'status' => 'issued', 'issued_at' => now(), 'recipient_snapshot' => ['name' => 'Camille Martin', 'email' => 'camille@example.test', 'country_code' => 'FR'],
            'pdf_private_document_id' => $document->id,
        ], $attributes))->saveQuietly();

        return $invoice->fresh();
    }
}
