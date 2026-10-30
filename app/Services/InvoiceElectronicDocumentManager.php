<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrganizationIntegration;
use App\Models\PrivateDocument;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class InvoiceElectronicDocumentManager
{
    /** @return array{document: PrivateDocument, valid: bool} */
    public function generateAndValidate(Invoice $invoice): array
    {
        $invoice->loadMissing('transmissions');
        $integration = OrganizationIntegration::withoutGlobalScopes()
            ->where('organization_id', $invoice->organization_id)
            ->where('provider', 'super_pdp')->where('name', 'production')->where('status', 'active')->first();
        $credentials = $integration?->credentials ?? [];
        $clientId = $credentials['client_id'] ?? null;
        $clientSecret = $credentials['client_secret'] ?? null;
        if (! is_string($clientId) || ! is_string($clientSecret)) {
            throw new LogicException('Connectez Super PDP en production avant de générer le document électronique.');
        }

        $payload = app(En16931InvoiceFactory::class)->make($invoice);
        $content = app(SuperPdpClient::class)->convertEn16931ToCii($clientId, $clientSecret, $payload);
        if (! str_contains($content, '<')) {
            throw new LogicException('Super PDP n’a pas retourné de document CII exploitable.');
        }
        $filename = 'Facture-'.$invoice->reference.'.xml';
        $path = 'documents/'.$invoice->organization_id.'/invoices/'.$invoice->public_id.'/'.$filename;
        $disk = (string) config('private-documents.disk', 'local');
        $report = app(SuperPdpClient::class)->validateCii($clientId, $clientSecret, $content, $filename);
        $validation = data_get($report, 'data.0', []);
        $valid = data_get($validation, 'is_valid') === true;

        try {
            Storage::disk($disk)->put($path, $content, ['visibility' => 'private']);
            $document = PrivateDocument::query()->create([
                'title' => 'Document électronique '.$invoice->reference,
                'category' => 'accounting_record', 'disk' => $disk, 'path' => $path, 'original_name' => $filename,
                'declared_mime_type' => 'application/xml', 'detected_mime_type' => 'application/xml',
                'size' => strlen($content), 'sha256' => hash('sha256', $content),
            ]);
            $transmission = $invoice->transmissions()->where('provider', 'super_pdp')->latest()->firstOrFail();
            $transmission->update([
                'electronic_private_document_id' => $document->id,
                'payload_sha256' => $document->sha256,
                'status' => $valid ? 'validated' : 'validation_failed',
                'last_error' => $valid ? null : (string) (data_get($validation, 'error') ?? 'Le document électronique a été refusé lors de la validation.'),
                'metadata' => array_merge($transmission->metadata ?? [], [
                    'message' => $valid ? 'Document CII validé par Super PDP. Il n’a pas été transmis.' : 'Document CII généré mais non valide : aucune transmission possible.',
                    'format' => 'cii', 'validation_report' => $validation,
                ]),
                'completed_at' => now(),
            ]);

            return ['document' => $document, 'valid' => $valid];
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
