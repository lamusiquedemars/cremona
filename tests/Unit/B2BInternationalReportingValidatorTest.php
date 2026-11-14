<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\InvoiceTransmission;
use App\Services\B2BInternationalReportingDraftFactory;
use App\Services\ElectronicReportingPaymentDraftFactory;
use App\Services\En16931InvoiceFactory;
use App\Services\B2BInternationalReportingValidator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class B2BInternationalReportingValidatorTest extends TestCase
{
    public function test_it_requires_an_international_company_identifier(): void
    {
        $invoice = new Invoice;
        $invoice->forceFill([
            'company_id' => 10,
            'reference' => 'FAC-2026-00001',
            'issued_on' => '2026-10-31',
            'recipient_snapshot' => ['country_code' => 'BE'],
        ]);
        $invoice->setRelation('lines', new Collection([new InvoiceLine]));

        $missing = app(B2BInternationalReportingValidator::class)->missing($invoice);

        $this->assertSame(['identifiant fiscal ou d’immatriculation du client'], $missing);
    }

    public function test_it_accepts_a_complete_international_company_invoice(): void
    {
        $invoice = new Invoice;
        $invoice->forceFill([
            'company_id' => 10,
            'reference' => 'FAC-2026-00001',
            'issued_on' => '2026-10-31',
            'recipient_snapshot' => ['country_code' => 'BE', 'vat_number' => 'BE0123456789'],
        ]);
        $invoice->setRelation('lines', new Collection([new InvoiceLine]));

        $this->assertSame([], app(B2BInternationalReportingValidator::class)->missing($invoice));
    }

    public function test_it_builds_a_non_submittable_b2b_international_draft(): void
    {
        $invoice = new Invoice;
        $invoice->forceFill(['reference' => 'FAC-2026-00001', 'issued_on' => '2026-10-31', 'currency' => 'EUR', 'subtotal_amount' => 100, 'tax_amount' => 20, 'total_amount' => 120, 'recipient_snapshot' => ['country_code' => 'BE', 'vat_number' => 'BE0123456789']]);
        $line = new InvoiceLine;
        $line->forceFill(['description' => 'Réparation', 'quantity' => 1, 'net_amount' => 100, 'tax_rate' => 20, 'tax_category' => 'standard']);
        $invoice->setRelation('lines', new Collection([$line]));

        $draft = app(B2BInternationalReportingDraftFactory::class)->make($invoice);

        $this->assertTrue($draft['draft_only']);
        $this->assertSame('out', $draft['direction']);
        $this->assertSame('BE0123456789', $draft['buyer']['tax_registration_id']);
        $this->assertSame('S', $draft['lines'][0]['tax_category_code']);
    }

    public function test_it_requires_a_super_pdp_invoice_event_for_a_payment_draft(): void
    {
        $invoice = new Invoice;
        $invoice->forceFill(['reference' => 'FAC-2026-00001', 'total_amount' => 120, 'subtotal_amount' => 100, 'tax_amount' => 20, 'currency' => 'EUR']);
        $invoice->setRelation('transmissions', new Collection([
            new InvoiceTransmission(['provider' => 'super_pdp', 'metadata' => ['events' => [['id' => 123]]]]),
        ]));
        $payment = new InvoicePayment;
        $payment->forceFill(['paid_on' => '2026-10-31', 'amount' => 60]);

        $draft = app(ElectronicReportingPaymentDraftFactory::class)->make($payment, $invoice);

        $this->assertSame(123, $draft['invoice_event_id']);
        $this->assertSame('60.00', $draft['subtotals'][0]['amount']);
    }

    public function test_it_includes_the_original_reference_in_a_credit_note_payload(): void
    {
        $original = new Invoice;
        $original->forceFill(['reference' => 'FAC-2026-00001', 'issued_on' => '2026-10-01']);
        $credit = new Invoice;
        $credit->forceFill([
            'kind' => 'credit_note', 'reference' => 'AV-2026-00001', 'issued_on' => '2026-10-31', 'issued_at' => '2026-10-31 10:00:00', 'currency' => 'EUR',
            'subtotal_amount' => 100, 'tax_amount' => 20, 'total_amount' => 120, 'balance_amount' => 120,
            'issuer_snapshot' => ['legal_name' => 'Atelier', 'email' => 'contact@example.test', 'address_line_1' => '1 rue', 'postal_code' => '75001', 'city' => 'Paris', 'country_code' => 'FR'],
            'recipient_snapshot' => ['name' => 'Client', 'address_line_1' => '2 rue', 'postal_code' => '1000', 'city' => 'Bruxelles', 'country_code' => 'BE'],
        ]);
        $line = new InvoiceLine;
        $line->forceFill(['position' => 1, 'description' => 'Correction', 'quantity' => 1, 'unit_amount' => 100, 'net_amount' => 100, 'tax_amount' => 20, 'tax_rate' => 20, 'tax_category' => 'standard']);
        $credit->setRelation('lines', new Collection([$line]));
        $credit->setRelation('creditNoteFor', $original);

        $payload = app(En16931InvoiceFactory::class)->make($credit);

        $this->assertSame('FAC-2026-00001', $payload['preceding_invoice_reference']['number']);
    }
}
