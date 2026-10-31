<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;

class ElectronicReportingPaymentDraftFactory
{
    /** @return array<string, mixed> */
    public function make(InvoicePayment $payment, Invoice $invoice): array
    {
        $invoice->loadMissing('transmissions');
        $eventId = $invoice->transmissions
            ->where('provider', 'super_pdp')
            ->flatMap(fn ($transmission) => data_get($transmission->metadata, 'events', []))
            ->pluck('id')->filter(fn (mixed $id): bool => is_numeric($id))->last();
        $ratio = (float) $invoice->total_amount > 0 ? (float) $payment->amount / (float) $invoice->total_amount : 0.0;

        return [
            'invoice_number' => $invoice->reference,
            'invoice_event_id' => is_numeric($eventId) ? (int) $eventId : null,
            'payment_date' => $payment->paid_on?->toDateString(),
            'subtotals' => [[
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency_code' => $invoice->currency,
                'tax_percent' => number_format((float) $invoice->tax_amount > 0 ? ((float) $invoice->tax_amount / max((float) $invoice->subtotal_amount, 0.01)) * 100 : 0, 2, '.', ''),
                'tax_amount' => number_format((float) $invoice->tax_amount * $ratio, 2, '.', ''),
            ]],
            'draft_only' => true,
        ];
    }
}
