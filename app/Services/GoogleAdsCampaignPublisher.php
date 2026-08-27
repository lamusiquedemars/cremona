<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class GoogleAdsCampaignPublisher
{
    public function __construct(
        private readonly GoogleAdsCampaignDraft $draft,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function publishPaused(Campaign $campaign, OrganizationIntegration $integration, ?User $actor = null): Campaign
    {
        if ($campaign->external_reference !== null) {
            throw new LogicException('Cette campagne est déjà liée à Google Ads.');
        }
        if ($integration->provider !== 'google_ads' || $integration->name !== 'reporting') {
            throw new LogicException('La connexion Google Ads de l’organisation est introuvable.');
        }

        $preview = $this->draft->preview($campaign);
        $client = new GoogleAdsApiClient($integration->credentials);
        $budget = $client->mutate('campaignBudgets', [[
            'create' => [
                'name' => $campaign->name.' — budget',
                'amountMicros' => (int) round($preview['campaign']['daily_budget'] * 1_000_000),
                'deliveryMethod' => 'STANDARD',
                'explicitlyShared' => false,
            ],
        ]]);
        $budgetResource = $budget['results'][0]['resourceName'] ?? null;
        if (! is_string($budgetResource) || $budgetResource === '') {
            throw new LogicException('Google Ads n’a pas renvoyé l’identifiant du budget.');
        }

        $created = $client->mutate('campaigns', [[
            'create' => [
                'name' => $campaign->name,
                'status' => 'PAUSED',
                'advertisingChannelType' => 'SEARCH',
                'campaignBudget' => $budgetResource,
                'manualCpc' => (object) [],
                'networkSettings' => [
                    'targetGoogleSearch' => true,
                    'targetSearchNetwork' => true,
                    'targetContentNetwork' => false,
                    'targetPartnerSearchNetwork' => false,
                ],
            ],
        ]]);
        $resource = $created['results'][0]['resourceName'] ?? null;
        if (! is_string($resource) || ! preg_match('#/campaigns/(\d+)$#', $resource, $matches)) {
            throw new LogicException('Google Ads n’a pas renvoyé l’identifiant de campagne.');
        }

        foreach ($preview['ad_groups'] as $group) {
            $adGroup = $client->mutate('adGroups', [[
                'create' => [
                    'name' => $group['name'],
                    'campaign' => $resource,
                    'status' => 'PAUSED',
                    'type' => 'SEARCH_STANDARD',
                    'cpcBidMicros' => 1_000_000,
                ],
            ]]);
            $adGroupResource = $adGroup['results'][0]['resourceName'] ?? null;
            if (! is_string($adGroupResource) || $adGroupResource === '') {
                throw new LogicException('Google Ads n’a pas renvoyé l’identifiant du groupe d’annonces.');
            }

            $criteria = [];
            foreach ($group['keywords'] as $keyword) {
                $criteria[] = ['create' => [
                    'adGroup' => $adGroupResource,
                    'status' => 'ENABLED',
                    'keyword' => ['text' => $keyword, 'matchType' => 'BROAD'],
                ]];
            }
            foreach ($group['negative_keywords'] as $keyword) {
                $criteria[] = ['create' => [
                    'adGroup' => $adGroupResource,
                    'negative' => true,
                    'keyword' => ['text' => $keyword, 'matchType' => 'BROAD'],
                ]];
            }
            if ($criteria !== []) {
                $client->mutate('adGroupCriteria', $criteria);
            }

            $client->mutate('adGroupAds', [[
                'create' => [
                    'adGroup' => $adGroupResource,
                    'status' => 'PAUSED',
                    'ad' => [
                        'finalUrls' => [$preview['campaign']['final_url']],
                        'responsiveSearchAd' => [
                            'headlines' => collect($group['headlines'])->map(fn (string $text): array => ['text' => $text])->all(),
                            'descriptions' => collect($group['descriptions'])->map(fn (string $text): array => ['text' => $text])->all(),
                        ],
                    ],
                ],
            ]]);
        }

        return DB::transaction(function () use ($campaign, $matches, $actor): Campaign {
            $campaign->update([
                'external_reference' => $matches[1],
                'status' => CampaignStatus::Paused,
            ]);
            $this->auditLogger->record('campaign.google_ads_created_paused', $campaign, $actor, [
                'google_campaign_id' => $matches[1],
            ]);

            return $campaign->refresh();
        });
    }

    public function activate(Campaign $campaign, OrganizationIntegration $integration, ?User $actor = null): Campaign
    {
        if ($campaign->channel !== 'google_ads' || blank($campaign->external_reference)) {
            throw new LogicException('Cette campagne Google Ads doit d’abord être créée en pause.');
        }
        if ($campaign->status !== CampaignStatus::Paused) {
            throw new LogicException('Seule une campagne en pause peut être activée.');
        }
        if ($integration->provider !== 'google_ads' || $integration->name !== 'reporting') {
            throw new LogicException('La connexion Google Ads de l’organisation est introuvable.');
        }

        $customerId = preg_replace('/\D/', '', (string) ($integration->credentials['customer_id'] ?? ''));
        if ($customerId === '' || ! ctype_digit((string) $campaign->external_reference)) {
            throw new LogicException('L’identifiant Google Ads de la campagne est invalide.');
        }

        $resourceName = "customers/{$customerId}/campaigns/{$campaign->external_reference}";
        (new GoogleAdsApiClient($integration->credentials))->mutate('campaigns', [[
            'updateMask' => 'status',
            'update' => [
                'resourceName' => $resourceName,
                'status' => 'ENABLED',
            ],
        ]]);

        return DB::transaction(function () use ($campaign, $actor): Campaign {
            $campaign->update(['status' => CampaignStatus::Active]);
            $this->auditLogger->record('campaign.google_ads_activated', $campaign, $actor, [
                'google_campaign_id' => $campaign->external_reference,
            ]);

            return $campaign->refresh();
        });
    }
}
