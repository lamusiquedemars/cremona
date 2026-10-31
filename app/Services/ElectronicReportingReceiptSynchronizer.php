<?php

namespace App\Services;

use App\Models\ElectronicReportingBatch;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;
use LogicException;
use Throwable;

class ElectronicReportingReceiptSynchronizer
{
    public function synchronize(Organization $organization): int
    {
        return app(OrganizationContext::class)->run($organization, function () use ($organization): int {
            $integration = OrganizationIntegration::withoutGlobalScopes()->where('organization_id', $organization->id)
                ->where('provider', 'super_pdp')->where('name', 'production')->where('status', 'active')->first();
            $credentials = $integration?->credentials ?? [];
            if (! is_string($credentials['client_id'] ?? null) || ! is_string($credentials['client_secret'] ?? null)) {
                throw new LogicException('Connectez Super PDP en production avant d’actualiser les accusés e-reporting.');
            }

            $cursor = null;
            $count = 0;
            do {
                $page = app(SuperPdpClient::class)->ereportings($credentials['client_id'], $credentials['client_secret'], $cursor);
                $batches = collect(data_get($page, 'data', []))->filter(fn (mixed $batch): bool => is_array($batch) && is_numeric(data_get($batch, 'id')))->values();
                foreach ($batches as $batch) {
                    ElectronicReportingBatch::query()->updateOrCreate(['remote_id' => (int) $batch['id']], [
                        'kind' => (string) data_get($batch, 'kind', 'unknown'),
                        'role_code' => (string) data_get($batch, 'role_code', 'SE'),
                        'start_period' => $this->date(data_get($batch, 'start_period')),
                        'end_period' => $this->date(data_get($batch, 'end_period')),
                        'events' => data_get($batch, 'events', []),
                        'synchronized_at' => now(),
                    ]);
                    $count++;
                }
                $lastId = $batches->pluck('id')->filter(fn (mixed $id): bool => is_numeric($id))->last();
                $nextCursor = is_numeric($lastId) ? (int) $lastId : null;
                if (data_get($page, 'has_more') !== true || $nextCursor === null || $nextCursor === $cursor) {
                    break;
                }
                $cursor = $nextCursor;
            } while (true);

            return $count;
        });
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
