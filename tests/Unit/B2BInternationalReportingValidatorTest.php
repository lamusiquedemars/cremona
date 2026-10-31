<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceLine;
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
}
