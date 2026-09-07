<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsReportingClient;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

class SyncGoogleAdsCampaignConfigurations extends Command
{
    protected $signature = 'cremona:sync-google-ads-configurations';

    protected $description = 'Relève en lecture seule la configuration des campagnes Google Ads connues.';

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

            [$synced, $organizationFailures] = $context->run($integration->organization, function () use ($integration, $reporting): array {
                $synced = 0;
                $failures = 0;

                foreach (Campaign::query()->where('channel', 'google_ads')->whereNotNull('external_reference')->get() as $campaign) {
                    try {
                        $reporting->syncCampaignConfiguration($campaign, $integration);
                        $synced++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error("{$integration->organization->name} — {$campaign->name}: {$exception->getMessage()}");
                        $failures++;
                    }
                }

                return [$synced, $failures];
            });
            $this->line("{$integration->organization->name}: {$synced} campagne(s) configurée(s) actualisée(s).");
            $failures += $organizationFailures;
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
