<?php

namespace App\Services;

use App\Models\IncomingInvoice;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\PrivateDocument;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class IncomingSuperPdpInvoiceSynchronizer
{
    /** @return array{created: int, updated: int, documents: int} */
    public function synchronize(Organization $organization): array
    {
        return app(OrganizationContext::class)->run($organization, function () use ($organization): array {
            $credentials = $this->credentials($organization);
            $cursor = IncomingInvoice::query()->where('provider', 'super_pdp')->max('remote_id');
            $result = ['created' => 0, 'updated' => 0, 'documents' => 0];

            for ($pageNumber = 0; $pageNumber < 100; $pageNumber++) {
                $page = app(SuperPdpClient::class)->incomingInvoices(
                    $credentials['client_id'],
                    $credentials['client_secret'],
                    is_numeric($cursor) ? (int) $cursor : null,
                );
                $invoices = collect(data_get($page, 'data', []))
                    ->filter(fn (mixed $invoice): bool => is_array($invoice) && is_numeric(data_get($invoice, 'id')))
                    ->values();

                foreach ($invoices as $remote) {
                    $stored = $this->storeInvoice($remote);
                    $result[$stored['created'] ? 'created' : 'updated']++;
                    $result['documents'] += $stored['document'] ? 1 : 0;
                }

                $lastId = $invoices->pluck('id')->filter(fn (mixed $id): bool => is_numeric($id))->last();
                $nextCursor = is_numeric($lastId) ? (int) $lastId : null;
                $hasNextPage = data_get($page, 'has_after') === true || data_get($page, 'has_more') === true;
                if (! $hasNextPage || $nextCursor === null || $nextCursor === $cursor) {
                    break;
                }
                $cursor = $nextCursor;
            }

            return $result;
        });
    }

    /** @param array<string, mixed> $remote
     *  @return array{created: bool, document: bool}
     */
    private function storeInvoice(array $remote): array
    {
        $remoteId = (int) data_get($remote, 'id');
        $incoming = IncomingInvoice::query()->firstOrNew(['provider' => 'super_pdp', 'remote_id' => $remoteId]);
        $created = ! $incoming->exists;
        $incoming->fill([
            'reference' => $this->string($remote, ['en_invoice.number', 'number', 'reference']),
            'supplier_name' => $this->string($remote, ['en_invoice.seller.name', 'seller.name', 'supplier.name']),
            'issued_on' => $this->date($remote, ['en_invoice.issue_date', 'issue_date', 'issued_on']),
            'status' => $this->string($remote, ['status', 'events.0.status_code']),
            'payload' => $remote,
            'synchronized_at' => now(),
        ])->save();

        if ($incoming->original_private_document_id !== null) {
            return ['created' => $created, 'document' => false];
        }

        $credentials = $this->credentials($incoming->organization);
        $original = app(SuperPdpClient::class)->originalInvoice($credentials['client_id'], $credentials['client_secret'], (string) $remoteId);
        if ($original['content'] === '') {
            throw new LogicException('Super PDP a retourné une facture reçue vide.');
        }

        $mimeType = $this->mimeType($original['mime_type']);
        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/xml', 'text/xml' => 'xml',
            default => 'bin',
        };
        $filename = 'Facture-recue-Super-PDP-'.$remoteId.'.'.$extension;
        $disk = (string) config('private-documents.disk', 'local');
        $path = 'documents/'.$incoming->organization_id.'/incoming-invoices/super-pdp-'.$remoteId.'/'.Str::uuid().'/'.$filename;

        try {
            if (! Storage::disk($disk)->put($path, $original['content'], ['visibility' => 'private'])) {
                throw new LogicException('La facture reçue n’a pas pu être conservée.');
            }

            $documentStored = DB::transaction(function () use ($incoming, $disk, $path, $filename, $mimeType, $original): bool {
                $locked = IncomingInvoice::query()->lockForUpdate()->findOrFail($incoming->id);
                if ($locked->original_private_document_id !== null) {
                    return false;
                }
                $document = PrivateDocument::query()->create([
                    'title' => 'Facture reçue '.($locked->reference ?: '#'.$locked->remote_id),
                    'category' => 'accounting_record',
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $filename,
                    'declared_mime_type' => $mimeType,
                    'detected_mime_type' => $mimeType,
                    'size' => strlen($original['content']),
                    'sha256' => hash('sha256', $original['content']),
                ]);
                $locked->update(['original_private_document_id' => $document->id]);

                return true;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return ['created' => $created, 'document' => $documentStored];
    }

    /** @return array{client_id: string, client_secret: string} */
    private function credentials(Organization $organization): array
    {
        $integration = OrganizationIntegration::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', 'super_pdp')->where('name', 'production')->where('status', 'active')->first();
        $credentials = $integration?->credentials ?? [];
        if (! is_string($credentials['client_id'] ?? null) || ! is_string($credentials['client_secret'] ?? null)) {
            throw new LogicException('Connectez Super PDP en production avant de synchroniser les factures reçues.');
        }

        return $credentials;
    }

    /** @param array<string, mixed> $data @param array<int, string> $paths */
    private function string(array $data, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($data, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data @param array<int, string> $paths */
    private function date(array $data, array $paths): ?string
    {
        $value = $this->string($data, $paths);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function mimeType(string $mimeType): string
    {
        return in_array($mimeType, ['application/pdf', 'application/xml', 'text/xml'], true)
            ? $mimeType
            : 'application/octet-stream';
    }
}
