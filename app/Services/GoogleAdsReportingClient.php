<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use Illuminate\Http\Client\RequestException;
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

        try {
            return $this->syncFromGoogle($integration, $organizationCredentials);
        } catch (RequestException $exception) {
            $message = $exception->response->json('error.message');
            $message = is_string($message) && $message !== ''
                ? $message
                : 'la requête a été refusée.';

            throw new LogicException("Google Ads a refusé la synchronisation : {$message}", previous: $exception);
        }
    }

    /** @param array<string, mixed> $organizationCredentials */
    private function syncFromGoogle(OrganizationIntegration $integration, array $organizationCredentials): int
    {
        $client = new GoogleAdsApiClient($organizationCredentials);
        $campaignStatusResponse = $client->searchStream(<<<'GAQL'
                    SELECT campaign.id, campaign.status, campaign.primary_status,
                        campaign.primary_status_reasons, campaign.serving_status,
                        campaign.bidding_strategy_system_status
                    FROM campaign
                    GAQL,
        );

        foreach ($this->rows($campaignStatusResponse) as $row) {
            if (! isset($row['campaign']['id'])) {
                continue;
            }

            $campaign = Campaign::query()
                ->where('channel', 'google_ads')
                ->where('external_reference', (string) $row['campaign']['id'])
                ->first();

            if ($campaign !== null) {
                $this->applyObservation($campaign, $row['campaign']);
            }
        }

        $response = $client->searchStream(<<<'GAQL'
                    SELECT campaign.id, campaign.name, campaign.status, campaign.primary_status,
                        campaign.primary_status_reasons, campaign.serving_status,
                        campaign.bidding_strategy_system_status, segments.date,
                        metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions
                    FROM campaign
                    WHERE segments.date DURING LAST_30_DAYS
                    GAQL,
        );
        $updated = 0;

        foreach ($this->rows($response) as $row) {
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
            $this->applyObservation($campaign, $row['campaign']);
            $updated++;
        }

        $integration->update([
            'credentials' => [...$organizationCredentials, 'last_synced_at' => now()->toIso8601String()],
        ]);

        return $updated;
    }

    /** @return iterable<int, array<string, mixed>> */
    private function rows(array $response): iterable
    {
        return collect($response)->pluck('results')->flatten(1)->filter(fn (mixed $row): bool => is_array($row));
    }

    /** @param array<string, mixed> $observation */
    private function applyObservation(Campaign $campaign, array $observation): void
    {
        $campaign->update([
            'google_ads_status' => $observation['status'] ?? null,
            'google_ads_primary_status' => $observation['primaryStatus'] ?? null,
            'google_ads_primary_status_reasons' => $observation['primaryStatusReasons'] ?? null,
            'google_ads_serving_status' => $observation['servingStatus'] ?? null,
            'google_ads_bidding_status' => $observation['biddingStrategySystemStatus'] ?? null,
            'google_ads_synced_at' => now(),
        ]);
    }
}
