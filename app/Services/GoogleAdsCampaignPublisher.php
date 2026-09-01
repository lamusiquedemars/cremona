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
        private readonly GoogleAdsCredentials $credentials,
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
        $client = new GoogleAdsApiClient($this->credentials->resolve($integration->credentials));
        $targetLocations = $this->resolveLocations($client, $preview['campaign']['target_locations'], $preview['campaign']['target_country']);
        $this->removeUnusedBudgetsNamed($client, $campaign->name.' — budget');

        $budgetPayload = [
            'name' => $campaign->name.' — budget',
            'deliveryMethod' => 'STANDARD',
            'explicitlyShared' => false,
        ];
        if ($preview['campaign']['budget_mode'] === 'total') {
            $budgetPayload['period'] = 'CUSTOM_PERIOD';
            $budgetPayload['totalAmountMicros'] = (int) round($preview['campaign']['total_budget'] * 1_000_000);
        } else {
            $budgetPayload['amountMicros'] = (int) round($preview['campaign']['daily_budget'] * 1_000_000);
        }

        $budget = $client->mutate('campaignBudgets', [['create' => $budgetPayload]]);
        $budgetResource = $budget['results'][0]['resourceName'] ?? null;
        if (! is_string($budgetResource) || $budgetResource === '') {
            throw new LogicException('Google Ads n’a pas renvoyé l’identifiant du budget.');
        }

        $campaignResource = null;

        try {
            $campaignPayload = [
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
            ];
            if ($preview['campaign']['budget_mode'] === 'total') {
                $campaignPayload['startDateTime'] = $this->googleAdsDateTime($preview['campaign']['starts_on'], false);
                $campaignPayload['endDateTime'] = $this->googleAdsDateTime($preview['campaign']['ends_on'], true);
            }

            $created = $client->mutate('campaigns', [['create' => $campaignPayload]]);
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

    private function googleAdsDateTime(string $date, bool $endOfDay): string
    {
        return str_replace('-', '', $date).($endOfDay ? ' 23:59:59' : ' 00:00:00');
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
        $client = new GoogleAdsApiClient($this->credentials->resolve($integration->credentials));
        $delivery = $this->enablePausedDeliveryResources($client, $resourceName);
        $client->mutate('campaigns', [[
            'updateMask' => 'status',
            'update' => [
                'resourceName' => $resourceName,
                'status' => 'ENABLED',
            ],
        ]]);

        return DB::transaction(function () use ($campaign, $actor, $delivery): Campaign {
            $campaign->update(['status' => CampaignStatus::Active]);
            $this->auditLogger->record('campaign.google_ads_activated', $campaign, $actor, [
                'google_campaign_id' => $campaign->external_reference,
                'enabled_ad_groups' => $delivery['ad_groups'],
                'enabled_ads' => $delivery['ads'],
            ]);

            return $campaign->refresh();
        });
    }

    /** @return array{ad_groups: int, ads: int} */
    private function enablePausedDeliveryResources(GoogleAdsApiClient $client, string $campaignResource): array
    {
        $adGroups = $this->pausedResourceNames($client, <<<GAQL
            SELECT ad_group.resource_name
            FROM ad_group
            WHERE ad_group.campaign = '{$campaignResource}'
                AND ad_group.status = 'PAUSED'
            GAQL, 'adGroup.resourceName');
        $ads = $this->pausedResourceNames($client, <<<GAQL
            SELECT ad_group_ad.resource_name
            FROM ad_group_ad
            WHERE campaign.resource_name = '{$campaignResource}'
                AND ad_group_ad.status = 'PAUSED'
            GAQL, 'adGroupAd.resourceName');

        $this->enableResources($client, 'adGroups', $adGroups);
        $this->enableResources($client, 'adGroupAds', $ads);

        return ['ad_groups' => count($adGroups), 'ads' => count($ads)];
    }

    /** @return array<int, string> */
    private function pausedResourceNames(GoogleAdsApiClient $client, string $query, string $field): array
    {
        return collect($client->searchStream($query))
            ->pluck('results')
            ->flatten(1)
            ->pluck($field)
            ->filter(fn (mixed $resourceName): bool => is_string($resourceName) && $resourceName !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<int, string> $resources */
    private function enableResources(GoogleAdsApiClient $client, string $service, array $resources): void
    {
        foreach (array_chunk($resources, 1000) as $chunk) {
            $client->mutate($service, array_map(fn (string $resourceName): array => [
                'updateMask' => 'status',
                'update' => ['resourceName' => $resourceName, 'status' => 'ENABLED'],
            ], $chunk));
        }
    }

    public function discardPaused(Campaign $campaign, OrganizationIntegration $integration, ?User $actor = null): Campaign
    {
        if ($campaign->channel !== 'google_ads' || blank($campaign->external_reference)) {
            throw new LogicException('Cette campagne Google Ads n’est pas liée à une campagne distante.');
        }
        if ($campaign->status !== CampaignStatus::Paused) {
            throw new LogicException('Seule une campagne Google Ads en pause peut être retirée.');
        }
        if ($campaign->dailyMetrics()->exists()) {
            throw new LogicException('Cette campagne possède déjà des données ; elle ne peut pas être retirée depuis Cremona.');
        }
        if ($integration->provider !== 'google_ads' || $integration->name !== 'reporting') {
            throw new LogicException('La connexion Google Ads de l’organisation est introuvable.');
        }

        $customerId = preg_replace('/\D/', '', (string) ($integration->credentials['customer_id'] ?? ''));
        if ($customerId === '' || ! ctype_digit((string) $campaign->external_reference)) {
            throw new LogicException('L’identifiant Google Ads de la campagne est invalide.');
        }

        $googleCampaignId = $campaign->external_reference;
        (new GoogleAdsApiClient($this->credentials->resolve($integration->credentials)))->mutate('campaigns', [[
            'remove' => "customers/{$customerId}/campaigns/{$googleCampaignId}",
        ]]);

        return DB::transaction(function () use ($campaign, $actor, $googleCampaignId): Campaign {
            $campaign->update([
                'external_reference' => null,
                'status' => CampaignStatus::Draft,
            ]);
            $this->auditLogger->record('campaign.google_ads_removed_before_launch', $campaign, $actor, [
                'google_campaign_id' => $googleCampaignId,
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
        $suggestions = collect($client->suggestGeoTargets(
            array_map(fn (string $name): string => $this->googleLocationQuery($name), $names),
            $countryCode,
            $countryCode === 'BR' ? 'pt-BR' : 'fr',
        ));

        foreach ($names as $name) {
            $matches = $suggestions
                ->filter(fn (mixed $suggestion): bool => is_array($suggestion)
                    && $this->matchesRequestedLocation($suggestion, $name)
                    && ($suggestion['geoTargetConstant']['countryCode'] ?? null) === $countryCode
                    && ($suggestion['geoTargetConstant']['status'] ?? null) === 'ENABLED'
                    && filled($suggestion['geoTargetConstant']['resourceName'] ?? null))
                ->pluck('geoTargetConstant')
                ->unique('resourceName')
                ->sortBy(fn (array $location): array => $this->locationPreference($location, $name))
                ->values();

            $best = $matches->first();
            $second = $matches->get(1);
            if ($best === null || ($second !== null && $this->locationPreference($best, $name) === $this->locationPreference($second, $name))) {
                throw new LogicException("Zone Google Ads introuvable ou ambiguë : {$name}.");
            }

            $resolved[] = $best['resourceName'];
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

    /** @param array<string, mixed> $suggestion */
    private function matchesRequestedLocation(array $suggestion, string $requestedName): bool
    {
        $location = $suggestion['geoTargetConstant'] ?? [];
        if (! is_array($location)) {
            return false;
        }

        $candidates = [
            $suggestion['searchTerm'] ?? null,
            $location['name'] ?? null,
            str($location['canonicalName'] ?? '')->before(',')->toString(),
        ];

        return collect($candidates)
            ->filter(fn (mixed $candidate): bool => is_string($candidate) && $candidate !== '')
            ->contains(fn (string $candidate): bool => $this->comparableLocationName($candidate) === $this->comparableLocationName($requestedName));
    }

    private function comparableLocationName(string $name): string
    {
        return preg_replace('/^(state|province|region) of\s+/i', '', $this->normaliseLocationName($name)) ?? $this->normaliseLocationName($name);
    }

    /** @param array<string, mixed> $location
     *  @return array{int, int, string}
     */
    private function locationPreference(array $location, string $requestedName): array
    {
        $type = (string) ($location['targetType'] ?? '');
        $typePriority = array_search($type, ['City', 'State', 'Province', 'Region', 'Department', 'Municipality', 'District'], true);
        $canonical = $this->normaliseLocationName((string) ($location['canonicalName'] ?? $location['name'] ?? ''));
        $needle = $this->normaliseLocationName($requestedName);

        return [
            $typePriority === false ? 99 : $typePriority,
            max(0, substr_count($canonical, $needle) - 1),
            (string) ($location['resourceName'] ?? ''),
        ];
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
