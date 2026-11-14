<?php

namespace App\Services;

use App\Models\Invoice;

class B2BInternationalReportingValidator
{
    /** @return array<int, string> */
    public function missing(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');
        $recipient = $invoice->recipient_snapshot ?? [];
        $missing = [];
        if ($invoice->company_id === null) {
            $missing[] = 'client professionnel';
        }
        if (blank($recipient['country_code'] ?? null) || strtoupper((string) ($recipient['country_code'] ?? '')) === 'FR') {
            $missing[] = 'pays étranger du client';
        }
        if (blank($recipient['registration_number'] ?? null) && blank($recipient['vat_number'] ?? null)) {
            $missing[] = 'identifiant fiscal ou d’immatriculation du client';
        }
        if (blank($invoice->reference) || $invoice->issued_on === null) {
            $missing[] = 'référence et date de facture';
        }
        if ($invoice->lines->isEmpty()) {
            $missing[] = 'lignes de facture';
        }

        return $missing;
    }
}
