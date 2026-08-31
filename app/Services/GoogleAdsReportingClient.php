<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use LogicException;

class GoogleAdsReportingClient
{
    public function __construct(private readonly GoogleAdsCredentials $credentials) {}

    public function sync(OrganizationIntegration $integration): int
    {
        $organizationCredentials = $integration->credentials;

        if (! $this->credentials->isReady($organizationCredentials)) {
            throw new LogicException('Google Ads n’est pas encore entièrement configuré.');
        }

        $response = (new GoogleAdsApiClient($organizationCredentials))->searchStream(<<<'GAQL'
                    SELECT campaign.id, campaign.name, campaign.status, segments.date,
                        metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions
                    FROM campaign
                    WHERE segments.date DURING LAST_30_DAYS
                    GAQL,
        );
        $updated = 0;

        foreach (collect($response)->pluck('results')->flatten(1) as $row) {
            if (! is_array($row) || ! isset($row['campaign']['id'], $row['segments']['date'])) {
                continue;
            }

            $campaign = Campaign::query()
                ->where('channel', 'google_ads')
                ->where('external_reference', (string) $row['campaign']['id'])
                ->first();

            if ($campaign === null) {
                continue;
            }

            $metrics = $row['metrics'] ?? [];
            $campaign->dailyMetrics()->updateOrCreate(
                ['metric_date' => $row['segments']['date'], 'source' => 'google_ads'],
                [
                    'spend' => ((float) ($metrics['costMicros'] ?? 0)) / 1_000_000,
                    'impressions' => (int) ($metrics['impressions'] ?? 0),
                    'clicks' => (int) ($metrics['clicks'] ?? 0),
                    'platform_conversions' => (float) ($metrics['conversions'] ?? 0),
                    'currency' => $campaign->currency,
                    'metadata' => ['google_ads_campaign_status' => $row['campaign']['status'] ?? null],
                ],
            );
            $updated++;
        }

        $integration->update([
            'credentials' => [...$organizationCredentials, 'last_synced_at' => now()->toIso8601String()],
        ]);

        return $updated;
    }
}
