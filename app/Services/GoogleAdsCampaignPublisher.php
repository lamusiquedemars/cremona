<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

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
        $targetLocations = $this->resolveLocations($client, $preview['campaign']['target_locations'], $preview['campaign']['target_country']);
        $this->removeUnusedBudgetsNamed($client, $campaign->name.' — budget');

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

        $campaignResource = null;

        try {
            $created = $client->mutate('campaigns', [[
                'create' => [
                    'name' => $campaign->name,
                    'status' => 'PAUSED',
                    'advertisingChannelType' => 'SEARCH',
                    'campaignBudget' => $budgetResource,
                    'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
                    // Google Ads API calls its standard "Maximize clicks" strategy TargetSpend.
                    'targetSpend' => (object) [],
                    'geoTargetTypeSetting' => [
                        'positiveGeoTargetType' => 'PRESENCE',
                    ],
                    'networkSettings' => [
                        'targetGoogleSearch' => true,
                        'targetSearchNetwork' => true,
                        'targetContentNetwork' => false,
                        'targetPartnerSearchNetwork' => false,
                    ],
                ],
            ]]);
            $campaignResource = $created['results'][0]['resourceName'] ?? null;
            if (! is_string($campaignResource) || ! preg_match('#/campaigns/(\d+)$#', $campaignResource, $matches)) {
                throw new LogicException('Google Ads n’a pas renvoyé l’identifiant de campagne.');
            }

            $this->applyCampaignTargeting($client, $campaignResource, $preview['campaign'], $targetLocations);

            foreach ($preview['ad_groups'] as $group) {
                $adGroup = $client->mutate('adGroups', [[
                    'create' => [
                        'name' => $group['name'],
                        'campaign' => $campaignResource,
                        'status' => 'PAUSED',
                        'type' => 'SEARCH_STANDARD',
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
                        'keyword' => $this->keyword($keyword),
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
        } catch (Throwable $exception) {
            $this->removePartiallyCreatedGoogleAdsResources($client, $campaignResource, $budgetResource);

            throw $exception;
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

    private function removePartiallyCreatedGoogleAdsResources(GoogleAdsApiClient $client, ?string $campaignResource, string $budgetResource): void
    {
        foreach (array_filter([$campaignResource, $budgetResource]) as $resourceName) {
            try {
                $client->mutate(str_contains($resourceName, '/campaigns/') ? 'campaigns' : 'campaignBudgets', [[
                    'remove' => $resourceName,
                ]]);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
        }
    }

    private function removeUnusedBudgetsNamed(GoogleAdsApiClient $client, string $name): void
    {
        $escapedName = str_replace("'", "\\\\'", $name);
        $query = <<<GAQL
            SELECT campaign_budget.resource_name
            FROM campaign_budget
            WHERE campaign_budget.name = '{$escapedName}'
                AND campaign_budget.reference_count = 0
                AND campaign_budget.status = 'ENABLED'
            GAQL;

        $budgetResources = collect($client->searchStream($query))
            ->pluck('results')
            ->flatten(1)
            ->pluck('campaignBudget.resourceName')
            ->filter(fn (mixed $resourceName): bool => is_string($resourceName) && $resourceName !== '')
            ->unique()
            ->values();

        foreach ($budgetResources as $budgetResource) {
            $client->mutate('campaignBudgets', [['remove' => $budgetResource]]);
        }
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

    /** @param array<string, mixed> $campaign */
    /** @param array<int, string> $targetLocations */
    private function applyCampaignTargeting(GoogleAdsApiClient $client, string $campaignResource, array $campaign, array $targetLocations): void
    {
        $operations = [];

        foreach ($targetLocations as $location) {
            $operations[] = ['create' => [
                'campaign' => $campaignResource,
                'location' => ['geoTargetConstant' => $location],
            ]];
        }

        foreach ($campaign['languages'] as $language) {
            $id = match (strtolower($language)) {
                'fr' => 1002,
                'pt' => 1014,
                default => throw new LogicException("Langue Google Ads non prise en charge : {$language}."),
            };
            $operations[] = ['create' => [
                'campaign' => $campaignResource,
                'language' => ['languageConstant' => "languageConstants/{$id}"],
            ]];
        }

        $client->mutate('campaignCriteria', $operations);
    }

    /** @param array<int, string> $names @return array<int, string> */
    private function resolveLocations(GoogleAdsApiClient $client, array $names, string $countryCode): array
    {
        $resolved = [];

        foreach ($names as $name) {
            $queryName = str_replace("'", "\\\\'", $this->googleLocationQuery($name));
            $query = <<<GAQL
                SELECT geo_target_constant.resource_name, geo_target_constant.name,
                    geo_target_constant.country_code, geo_target_constant.status
                FROM geo_target_constant
                WHERE geo_target_constant.name = '{$queryName}'
                    AND geo_target_constant.country_code = '{$countryCode}'
                    AND geo_target_constant.status = 'ENABLED'
                GAQL;
            $matches = collect($client->searchStream($query))
                ->pluck('results')
                ->flatten(1)
                ->pluck('geoTargetConstant')
                ->filter(fn (mixed $location): bool => is_array($location)
                    && $this->normaliseLocationName((string) ($location['name'] ?? '')) === $this->normaliseLocationName($name)
                    && ($location['countryCode'] ?? null) === $countryCode
                    && filled($location['resourceName'] ?? null))
                ->unique('resourceName')
                ->values();

            if ($matches->count() !== 1) {
                throw new LogicException("Zone Google Ads introuvable ou ambiguë : {$name}.");
            }

            $resolved[] = $matches->sole()['resourceName'];
        }

        return $resolved;
    }

    private function normaliseLocationName(string $name): string
    {
        return Str::lower($this->googleLocationQuery($name));
    }

    private function googleLocationQuery(string $name): string
    {
        return trim(Str::ascii($name));
    }

    /** @return array{text: string, matchType: string} */
    private function keyword(string $keyword): array
    {
        $keyword = trim($keyword);
        $matchType = 'BROAD';

        if (preg_match('/^\[(.+)]$/u', $keyword, $matches)) {
            $keyword = trim($matches[1]);
            $matchType = 'EXACT';
        } elseif (preg_match('/^"(.+)"$/u', $keyword, $matches)) {
            $keyword = trim($matches[1]);
            $matchType = 'PHRASE';
        }

        if ($keyword === '') {
            throw new LogicException('Un mot-clé ne peut pas être vide.');
        }

        return ['text' => $keyword, 'matchType' => $matchType];
    }
}
