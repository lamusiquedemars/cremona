<?php

namespace App\Services;

use App\Models\ElectronicReport;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\OrganizationIntegration;
use LogicException;

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

    /** @return array<string, mixed> */
    public function preview(ElectronicReport $report): array
    {
        if (! in_array($report->status, ['pending_review', 'previewed'], true)) {
            throw new LogicException('Cette opération ne peut plus être prévisualisée dans son état actuel.');
        }
        $integration = OrganizationIntegration::withoutGlobalScopes()
            ->where('organization_id', $report->organization_id)
            ->where('provider', 'super_pdp')->where('name', 'production')->where('status', 'active')->first();
        $credentials = $integration?->credentials ?? [];
        if (! is_string($credentials['client_id'] ?? null) || ! is_string($credentials['client_secret'] ?? null)) {
            throw new LogicException('Connectez Super PDP en production avant de prévisualiser l’e-reporting.');
        }

        $preview = app(SuperPdpClient::class)->ereportingPreview(
            $credentials['client_id'],
            $credentials['client_secret'],
            $report->occurred_on->toDateString(),
            $report->kind,
        );
        $report->update([
            'status' => 'previewed',
            'payload' => array_merge($report->payload, ['super_pdp_preview' => $preview, 'previewed_at' => now()->toIso8601String()]),
            'last_error' => null,
        ]);

        return $preview;
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
