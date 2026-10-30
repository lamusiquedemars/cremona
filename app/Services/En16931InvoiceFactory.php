<?php

namespace App\Services;

use App\Models\Invoice;
use LogicException;

class En16931InvoiceFactory
{
    /** @return array<string, mixed> */
    public function make(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');
        if ($invoice->issued_at === null || blank($invoice->reference)) {
            throw new LogicException('Émettez la facture avant de générer son document électronique.');
        }

        $issuer = $invoice->issuer_snapshot ?? [];
        $recipient = $invoice->recipient_snapshot ?? [];
        $this->requireAddress($issuer, 'émetteur');
        $this->requireAddress($recipient, 'client');

        $vatBreakdown = $invoice->lines->groupBy(fn ($line): string => (string) $line->tax_rate)
            ->map(function ($lines, string $rate): array {
                $taxable = round((float) $lines->sum('net_amount'), 2);
                $tax = round((float) $lines->sum('tax_amount'), 2);
                $category = (float) $rate === 0.0 ? 'E' : 'S';
                $breakdown = [
                    'vat_category_code' => $category,
                    'vat_category_taxable_amount' => $this->decimal($taxable),
                    'vat_category_tax_amount' => $this->decimal($tax),
                ];
                if ($category === 'S') {
                    $breakdown['vat_category_rate'] = $this->decimal((float) $rate);
                } else {
                    $breakdown['vat_exemption_reason'] = 'TVA non applicable, art. 293 B du CGI';
                }

                return $breakdown;
            })->values()->all();

        return [
            'number' => $invoice->reference,
            'issue_date' => $invoice->issued_on?->format('Y-m-d') ?? $invoice->issued_at->format('Y-m-d'),
            'type_code' => $invoice->kind === 'credit_note' ? 381 : 380,
            'currency_code' => $invoice->currency,
            'process_control' => ['specification_identifier' => 'urn:cen.eu:en16931:2017'],
            'seller' => $this->party($issuer, true),
            'buyer' => $this->party($recipient, false),
            'payment_due_date' => $invoice->due_on?->format('Y-m-d'),
            'payment_terms' => $invoice->payment_terms,
            'totals' => [
                'sum_invoice_lines_amount' => $this->decimal((float) $invoice->subtotal_amount),
                'total_without_vat' => $this->decimal((float) $invoice->subtotal_amount),
                'total_with_vat' => $this->decimal((float) $invoice->total_amount),
                'amount_due_for_payment' => $this->decimal((float) $invoice->balance_amount),
                'total_vat_amount' => ['amount' => $this->decimal((float) $invoice->tax_amount), 'currency_code' => $invoice->currency],
            ],
            'vat_break_down' => $vatBreakdown,
            'lines' => $invoice->lines->map(fn ($line): array => [
                'identifier' => (string) $line->position,
                'invoiced_quantity' => $this->decimal((float) $line->quantity),
                'invoiced_quantity_code' => 'C62',
                'net_amount' => $this->decimal((float) $line->net_amount),
                'price_details' => [
                    'item_net_price' => $this->decimal((float) $line->unit_amount),
                    'item_price_base_quantity' => '1',
                    'quantity_unit_code' => 'C62',
                ],
                'item_information' => ['name' => str($line->description)->limit(100, '')->toString(), 'description' => $line->description],
                'vat_information' => array_filter([
                    'invoiced_item_vat_category_code' => (float) $line->tax_rate === 0.0 ? 'E' : 'S',
                    'invoiced_item_vat_rate' => (float) $line->tax_rate === 0.0 ? null : $this->decimal((float) $line->tax_rate),
                    'exemption_reason' => (float) $line->tax_rate === 0.0 ? 'TVA non applicable, art. 293 B du CGI' : null,
                ]),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $party @return array<string, mixed> */
    private function party(array $party, bool $seller): array
    {
        $result = [
            'name' => $party['legal_name'] ?? $party['name'],
            'postal_address' => [
                'address_line1' => $party['address_line_1'],
                'address_line2' => $party['address_line_2'] ?? null,
                'post_code' => $party['postal_code'],
                'city' => $party['city'],
                'country_code' => $party['country_code'],
            ],
        ];
        if ($seller) {
            $result['electronic_address'] = ['scheme' => 'EM', 'value' => $party['email']];
            if (filled($party['vat_number'] ?? null)) {
                $result['vat_identifier'] = $party['vat_number'];
            }
        } elseif (filled($party['vat_number'] ?? null)) {
            $result['vat_identifier'] = $party['vat_number'];
        }
        if (filled($party['registration_number'] ?? null)) {
            $number = preg_replace('/\s+/', '', (string) $party['registration_number']);
            $result['identifiers'] = [['scheme' => strlen($number) === 14 ? '0002' : '0009', 'value' => $number]];
        }

        return array_filter($result, fn ($value): bool => $value !== null);
    }

    /** @param array<string, mixed> $party */
    private function requireAddress(array $party, string $label): void
    {
        foreach (['name', 'address_line_1', 'postal_code', 'city', 'country_code'] as $field) {
            $value = $field === 'name' ? ($party['legal_name'] ?? $party['name'] ?? null) : ($party[$field] ?? null);
            if (blank($value)) {
                throw new LogicException("Complétez l’adresse du {$label} avant la validation électronique.");
            }
        }
        if ($label === 'émetteur' && blank($party['email'] ?? null)) {
            throw new LogicException('Complétez l’e-mail de l’émetteur avant la validation électronique.');
        }
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
