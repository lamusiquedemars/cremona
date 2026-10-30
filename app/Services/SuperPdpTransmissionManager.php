<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTransmission;
use App\Models\OrganizationIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

class SuperPdpTransmissionManager
{
    public function submit(Invoice $invoice): InvoiceTransmission
    {
        if ($invoice->company_id !== null && blank(data_get($invoice->recipient_snapshot, 'registration_number'))) {
            throw new LogicException('Renseignez le SIREN ou SIRET du client professionnel avant la transmission.');
        }

        return DB::transaction(function () use ($invoice): InvoiceTransmission {
            $transmission = InvoiceTransmission::query()->where('invoice_id', $invoice->id)->where('provider', 'super_pdp')
                ->lockForUpdate()->latest()->firstOrFail();
            if (! in_array($transmission->status, ['validated', 'submission_failed'], true) || $transmission->electronic_private_document_id === null) {
                throw new LogicException('Validez d’abord le document électronique avant sa transmission.');
            }
            $document = $transmission->electronicPrivateDocument()->firstOrFail();
            $content = Storage::disk($document->disk)->get($document->path);
            $credentials = $this->credentials($invoice);
            $externalId = $transmission->external_id ?: 'crm-'.$invoice->public_id;
            $transmission->update(['status' => 'submitting', 'external_id' => $externalId, 'last_error' => null]);

            try {
                $remote = app(SuperPdpClient::class)->submitCii($credentials['client_id'], $credentials['client_secret'], $content, $externalId, $this->processingRule($invoice));
            } catch (\Throwable $exception) {
                $transmission->update(['status' => 'submission_failed', 'last_error' => 'Super PDP n’a pas accepté la transmission.']);
                throw $exception;
            }

            $transmission->update([
                'status' => 'submitted', 'submitted_at' => now(),
                'metadata' => array_merge($transmission->metadata ?? [], ['message' => 'Facture remise à Super PDP. Les statuts de traitement restent à suivre.', 'super_pdp_invoice_id' => data_get($remote, 'id')]),
            ]);
            $invoice->forceFill(['sent_at' => now()])->saveQuietly();

            return $transmission->fresh();
        });
    }

    public function synchronize(Invoice $invoice): InvoiceTransmission
    {
        $transmission = InvoiceTransmission::query()->where('invoice_id', $invoice->id)->where('provider', 'super_pdp')->latest()->firstOrFail();
        $remoteId = data_get($transmission->metadata, 'super_pdp_invoice_id');
        if (! is_numeric($remoteId)) {
            throw new LogicException('La facture n’a pas encore été remise à Super PDP.');
        }
        $credentials = $this->credentials($invoice);
        $events = app(SuperPdpClient::class)->invoiceEvents($credentials['client_id'], $credentials['client_secret'], (string) $remoteId);
        $codes = collect(data_get($events, 'data', []))->pluck('status_code')->filter()->values()->all();
        $status = in_array('api:rejected', $codes, true) || in_array('fr:210', $codes, true) ? 'rejected'
            : (in_array('api:sent', $codes, true) || in_array('fr:201', $codes, true) ? 'sent' : 'submitted');
        $transmission->update([
            'status' => $status,
            'completed_at' => in_array($status, ['sent', 'rejected'], true) ? now() : null,
            'last_error' => $status === 'rejected' ? 'Super PDP a signalé un rejet. Consultez le journal des événements.' : null,
            'metadata' => array_merge($transmission->metadata ?? [], ['message' => $status === 'rejected' ? 'Facture rejetée par le réseau de facturation.' : 'Statuts Super PDP synchronisés.', 'events' => data_get($events, 'data', [])]),
        ]);

        return $transmission->fresh();
    }

    /** @return array{client_id: string, client_secret: string} */
    private function credentials(Invoice $invoice): array
    {
        $integration = OrganizationIntegration::withoutGlobalScopes()->where('organization_id', $invoice->organization_id)
            ->where('provider', 'super_pdp')->where('name', 'production')->where('status', 'active')->first();
        $credentials = $integration?->credentials ?? [];
        if (! is_string($credentials['client_id'] ?? null) || ! is_string($credentials['client_secret'] ?? null)) {
            throw new LogicException('Connectez Super PDP en production avant toute transmission.');
        }

        return $credentials;
    }

    private function processingRule(Invoice $invoice): string
    {
        return $invoice->company_id === null ? 'B2C' : 'B2B';
    }
}
