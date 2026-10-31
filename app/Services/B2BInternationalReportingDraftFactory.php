<?php

namespace App\Services;

use App\Models\Invoice;

class B2BInternationalReportingDraftFactory
{
    /** @return array<string, mixed> */
    public function make(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');
        $recipient = $invoice->recipient_snapshot ?? [];

        return [
            'direction' => 'out',
            'invoice_number' => $invoice->reference,
            'issue_date' => $invoice->issued_on?->toDateString(),
            'due_date' => $invoice->due_on?->toDateString(),
            'currency_code' => $invoice->currency,
            'buyer' => [
                'country' => $recipient['country_code'] ?? null,
                'company_id' => $recipient['registration_number'] ?? null,
                'tax_registration_id' => $recipient['vat_number'] ?? null,
            ],
            'lines' => $invoice->lines->map(fn ($line): array => [
                'description' => $line->description,
                'billed_quantity' => (string) $line->quantity,
                'billed_quantity_unit_code' => 'C62',
                'net_amount' => (string) $line->net_amount,
                'tax_category_code' => match ($line->tax_category) {
                    'standard' => 'S', 'exempt' => 'E', 'zero_rated' => 'Z', 'reverse_charge' => 'AE', 'out_of_scope' => 'O', default => null,
                },
                'tax_percent' => (string) $line->tax_rate,
            ])->all(),
            'totals' => [
                'tax_exclusive_amount' => (string) $invoice->subtotal_amount,
                'tax_total' => (string) $invoice->tax_amount,
                'tax_inclusive_amount' => (string) $invoice->total_amount,
            ],
            'draft_only' => true,
            'note' => 'Brouillon de rapprochement : la soumission B2B international reste désactivée jusqu’à la recette sandbox du schéma Super PDP.',
        ];
    }
}
