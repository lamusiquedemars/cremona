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

        $transmission = DB::transaction(function () use ($invoice): InvoiceTransmission {
            $transmission = InvoiceTransmission::query()->where('invoice_id', $invoice->id)->where('provider', 'super_pdp')
                ->lockForUpdate()->latest()->firstOrFail();
            if ($transmission->status !== 'validated' || $transmission->electronic_private_document_id === null) {
                throw new LogicException('Validez d’abord le document électronique avant sa transmission.');
            }
            $externalId = $transmission->external_id ?: 'crm-'.$invoice->public_id;
            $transmission->update(['status' => 'submitting', 'external_id' => $externalId, 'last_error' => null]);

            return $transmission;
        });

        try {
            $document = $transmission->electronicPrivateDocument()->firstOrFail();
            $content = Storage::disk($document->disk)->get($document->path);
            $credentials = $this->credentials($invoice);
            $remote = app(SuperPdpClient::class)->submitCii($credentials['client_id'], $credentials['client_secret'], $content, $transmission->external_id, $this->processingRule($invoice));
            if (! is_numeric(data_get($remote, 'id'))) {
                throw new LogicException('La réponse Super PDP ne contient pas d’identifiant de facture.');
            }
        } catch (\Throwable $exception) {
            $transmission->update(['status' => 'submission_unknown', 'last_error' => 'Résultat de transmission inconnu. Vérifiez Super PDP avant toute nouvelle tentative.']);
            throw $exception;
        }

        return DB::transaction(function () use ($invoice, $transmission, $remote): InvoiceTransmission {
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
        $events = $this->allEvents($credentials['client_id'], $credentials['client_secret'], (string) $remoteId);
        $codes = collect($events)->pluck('status_code')->filter()->values()->all();
        $status = in_array('api:rejected', $codes, true) || in_array('fr:213', $codes, true) ? 'rejected'
            : (in_array('fr:210', $codes, true) ? 'refused'
                : (in_array('api:invalid', $codes, true) || in_array('fr:501', $codes, true) ? 'invalid'
                    : (in_array('api:sent', $codes, true) || in_array('fr:201', $codes, true) ? 'sent' : 'submitted')));
        $transmission->update([
            'status' => $status,
            'completed_at' => in_array($status, ['sent', 'rejected', 'refused', 'invalid'], true) ? now() : null,
            'last_error' => match ($status) {
                'rejected' => 'Super PDP a signalé un rejet. Consultez le journal des événements.',
                'refused' => 'Le destinataire a refusé la facture. Consultez le journal des événements.',
                'invalid' => 'Super PDP a signalé un document invalide ou inadmissible. Consultez le journal des événements.',
                default => null,
            },
            'metadata' => array_merge($transmission->metadata ?? [], ['message' => $status === 'rejected' ? 'Facture rejetée par le réseau de facturation.' : 'Statuts Super PDP synchronisés.', 'events' => $events]),
        ]);

        return $transmission->fresh();
    }

    /** @return array<int, array<string, mixed>> */
    private function allEvents(string $clientId, string $clientSecret, string $invoiceId): array
    {
        $events = [];
        $cursor = null;

        do {
            $previousCursor = $cursor;
            $page = app(SuperPdpClient::class)->invoiceEvents($clientId, $clientSecret, $invoiceId, $cursor);
            $data = collect(data_get($page, 'data', []))->filter(fn ($event): bool => is_array($event))->values();
            foreach ($data as $event) {
                $events[(string) data_get($event, 'id', count($events))] = $event;
            }
            $lastId = $data->pluck('id')->filter(fn ($id): bool => is_numeric($id))->last();
            $cursor = is_numeric($lastId) ? (int) $lastId : null;
        } while (data_get($page, 'has_after') === true && $cursor !== null && $cursor !== $previousCursor);

        return array_values($events);
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
        if ($invoice->company_id === null) {
            return 'B2C';
        }

        return strtoupper((string) data_get($invoice->recipient_snapshot, 'country_code')) === 'FR' ? 'B2B' : 'B2BInt';
    }
}
