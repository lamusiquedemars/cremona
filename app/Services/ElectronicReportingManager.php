<?php

namespace App\Services;

use App\Models\ElectronicReport;
use App\Models\Invoice;
use App\Models\InvoicePayment;

class ElectronicReportingManager
{
    public function queueInvoice(Invoice $invoice): ?ElectronicReport
    {
        $scope = $this->scope($invoice);
        if ($scope === null) {
            return null;
        }

        return ElectronicReport::query()->updateOrCreate(
            ['source_type' => Invoice::class, 'source_id' => $invoice->id, 'kind' => 'transaction'],
            [
                'invoice_id' => $invoice->id,
                'scope' => $scope,
                'status' => 'pending_review',
                'occurred_on' => $invoice->issued_on,
                'amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'payload' => $this->invoicePayload($invoice, $scope),
                'remote_id' => null,
                'submitted_at' => null,
                'acknowledged_at' => null,
                'last_error' => null,
            ],
        );
    }

    public function queuePayment(InvoicePayment $payment): ?ElectronicReport
    {
        $invoice = $payment->invoice()->firstOrFail();
        $scope = $this->scope($invoice);
        if ($scope === null) {
            return null;
        }

        return ElectronicReport::query()->updateOrCreate(
            ['source_type' => InvoicePayment::class, 'source_id' => $payment->id, 'kind' => 'payment'],
            [
                'invoice_id' => $invoice->id,
                'scope' => $scope,
                'status' => 'pending_review',
                'occurred_on' => $payment->paid_on,
                'amount' => $payment->amount,
                'currency' => $invoice->currency,
                'payload' => [
                    'invoice_reference' => $invoice->reference,
                    'invoice_public_id' => $invoice->public_id,
                    'payment_method' => $payment->method,
                    'payment_reference' => $payment->reference,
                    'recipient' => $invoice->recipient_snapshot,
                ],
                'remote_id' => null,
                'submitted_at' => null,
                'acknowledged_at' => null,
                'last_error' => null,
            ],
        );
    }

    private function scope(Invoice $invoice): ?string
    {
        if ($invoice->company_id === null) {
            return 'b2c';
        }

        return strtoupper((string) data_get($invoice->recipient_snapshot, 'country_code')) === 'FR'
            ? null
            : 'b2b_international';
    }

    /** @return array<string, mixed> */
    private function invoicePayload(Invoice $invoice, string $scope): array
    {
        return [
            'invoice_reference' => $invoice->reference,
            'invoice_public_id' => $invoice->public_id,
            'scope' => $scope,
            'kind' => $invoice->kind,
            'issued_on' => $invoice->issued_on?->toDateString(),
            'supply_on' => $invoice->supply_on?->toDateString(),
            'subtotal_amount' => $invoice->subtotal_amount,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'issuer' => $invoice->issuer_snapshot,
            'recipient' => $invoice->recipient_snapshot,
        ];
    }
}
