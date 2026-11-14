<?php

namespace App\Console\Commands;

use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsReportingClient;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

class SyncGoogleAdsReporting extends Command
{
    protected $signature = 'cremona:sync-google-ads';

    protected $description = 'Synchronise en lecture seule les résultats Google Ads de chaque organisation active.';

    public function handle(GoogleAdsReportingClient $reporting, OrganizationContext $context): int
    {
        $integrations = OrganizationIntegration::withoutGlobalScopes()
            ->with('organization')
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->where('status', 'active')
            ->get();
        $failures = 0;

        foreach ($integrations as $integration) {
            if ($integration->organization === null || $integration->organization->status !== 'active') {
                continue;
            }

            try {
                $updated = $context->run(
                    $integration->organization,
                    fn (): int => $reporting->sync($integration),
                );
                $this->line("{$integration->organization->name}: {$updated} journée(s) actualisée(s).");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$integration->organization->name}: {$exception->getMessage()}");
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
