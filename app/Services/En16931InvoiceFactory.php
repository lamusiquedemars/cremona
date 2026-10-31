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

        $vatBreakdown = $invoice->lines->groupBy(fn ($line): string => implode('|', [
            $line->tax_category, $line->tax_rate, $line->tax_exemption_reason,
        ]))->map(function ($lines): array {
            $first = $lines->first();
            $vat = $this->vatInformation($first);
            $taxable = round((float) $lines->sum('net_amount'), 2);
            $tax = round((float) $lines->sum('tax_amount'), 2);
            $breakdown = [
                'vat_category_code' => $vat['code'],
                'vat_category_taxable_amount' => $this->decimal($taxable),
                'vat_category_tax_amount' => $this->decimal($tax),
            ];
            if ($vat['rate'] !== null) {
                $breakdown['vat_category_rate'] = $vat['rate'];
            }
            if ($vat['reason'] !== null) {
                $breakdown['vat_exemption_reason'] = $vat['reason'];
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
            'lines' => $invoice->lines->map(function ($line): array {
                $vat = $this->vatInformation($line);

                return [
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
                        'invoiced_item_vat_category_code' => $vat['code'],
                        'invoiced_item_vat_rate' => $vat['rate'],
                        'exemption_reason' => $vat['reason'],
                    ]),
                ];
            })->all(),
        ];
    }

    /** @return array{code: string, rate: ?string, reason: ?string} */
    private function vatInformation(object $line): array
    {
        $category = (string) $line->tax_category;
        $rate = (float) $line->tax_rate;
        $code = match ($category) {
            'standard' => 'S',
            'exempt' => 'E',
            'zero_rated' => 'Z',
            'reverse_charge' => 'AE',
            'out_of_scope' => 'O',
            default => throw new LogicException('La catégorie TVA de chaque ligne doit être renseignée.'),
        };
        if ($category === 'standard' && $rate <= 0) {
            throw new LogicException('Une ligne à TVA à 0 % doit utiliser une catégorie et un motif TVA explicites.');
        }
        if ($category !== 'standard' && $rate !== 0.0) {
            throw new LogicException('Une ligne exonérée, à taux zéro, autoliquidée ou hors champ doit avoir une TVA à 0 %.');
        }
        $reason = trim((string) $line->tax_exemption_reason);
        if ($category !== 'standard' && $reason === '') {
            throw new LogicException('Complétez le motif TVA de chaque ligne à TVA non standard.');
        }

        return ['code' => $code, 'rate' => $category === 'standard' ? $this->decimal($rate) : null, 'reason' => $reason !== '' ? $reason : null];
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
