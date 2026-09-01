<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsReportingClient;
use App\Services\GoogleAdsCampaignPublisher;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAdsReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_ads_sync_updates_only_known_campaigns_from_aggregated_daily_metrics(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            'googleads.googleapis.com/*' => Http::response([[
                'results' => [[
                    'campaign' => [
                        'id' => '123',
                        'name' => 'Defesa penal',
                        'status' => 'ENABLED',
                        'primaryStatus' => 'LEARNING',
                        'primaryStatusReasons' => ['BIDDING_STRATEGY_LEARNING'],
                        'servingStatus' => 'SERVING',
                        'biddingStrategySystemStatus' => 'LEARNING',
                    ],
                    'segments' => ['date' => '2026-08-22'],
                    'metrics' => [
                        'costMicros' => '125500000',
                        'impressions' => '1200',
                        'clicks' => '42',
                        'conversions' => 3.5,
                    ],
                ], [
                    'campaign' => ['id' => '999', 'name' => 'Unknown', 'status' => 'ENABLED'],
                    'segments' => ['date' => '2026-08-22'],
                    'metrics' => ['costMicros' => '1000000'],
                ]],
            ]]),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            Campaign::query()->create([
                'name' => 'Defesa penal Cuiabá',
                'channel' => 'google_ads',
                'tracking_key' => 'criminal-cuiaba',
                'external_reference' => '123',
                'status' => CampaignStatus::Active,
                'currency' => 'BRL',
            ]);
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads',
                'name' => 'reporting',
                'status' => 'active',
                'credentials' => [
                    'customer_id' => '2005073692',
                    'developer_token' => 'developer-token',
                    'oauth_client_id' => 'client-id',
                    'oauth_client_secret' => 'client-secret',
                    'refresh_token' => 'refresh-token',
                ],
            ]);

            $this->assertSame(1, app(GoogleAdsReportingClient::class)->sync($integration));
            $metric = Campaign::query()->sole()->dailyMetrics()->sole();
            $this->assertSame('125.50', $metric->spend);
            $this->assertSame('3.50', $metric->platform_conversions);
            $this->assertSame(42, $metric->clicks);
            $campaign = Campaign::query()->sole();
            $this->assertSame('LEARNING', $campaign->google_ads_primary_status);
            $this->assertSame(['BIDDING_STRATEGY_LEARNING'], $campaign->google_ads_primary_status_reasons);
            $this->assertSame('SERVING', $campaign->google_ads_serving_status);
            $this->assertSame('LEARNING', $campaign->google_ads_bidding_status);
            $this->assertNotNull($campaign->google_ads_synced_at);
        });

        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v25/customers/2005073692/googleAds:searchStream')
            && $request->hasHeader('developer-token', 'developer-token')
            && $request->hasHeader('Authorization', 'Bearer short-lived-token'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), ':mutate'));
    }

    public function test_results_sync_does_not_modify_the_existing_atelier_campaign(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            'googleads.googleapis.com/*' => Http::response([['results' => []]]),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo Incidit — Essai régional 2026',
                'channel' => 'google_ads',
                'tracking_key' => 'atelier-ivo-essai-regional-2026',
                'external_reference' => '123456789',
                'status' => CampaignStatus::Active,
                'currency' => 'EUR',
                'starts_on' => '2026-08-31',
                'ends_on' => '2026-09-30',
                'planned_budget' => 100,
                'configuration' => ['budget_mode' => 'total'],
            ]);
            $before = $campaign->fresh()->toArray();
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '2005073692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);

            $this->assertSame(0, app(GoogleAdsReportingClient::class)->sync($integration));
            $this->assertSame($before, $campaign->fresh()->toArray());
        });

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), ':mutate'));
    }

    public function test_google_ads_sync_keeps_an_observed_status_even_when_there_are_no_daily_metrics(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            'googleads.googleapis.com/*' => Http::sequence()
                ->push([['results' => [[
                    'campaign' => [
                        'id' => '123',
                        'status' => 'ENABLED',
                        'primaryStatus' => 'LEARNING',
                        'primaryStatusReasons' => ['BIDDING_STRATEGY_LEARNING'],
                    ],
                ]]]])
                ->push([['results' => []]]),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Recherche',
                'channel' => 'google_ads',
                'tracking_key' => 'atelier-archets',
                'external_reference' => '123',
                'status' => CampaignStatus::Active,
                'currency' => 'EUR',
            ]);
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '2005073692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);

            $this->assertSame(0, app(GoogleAdsReportingClient::class)->sync($integration));
            $this->assertSame('LEARNING', $campaign->fresh()->google_ads_primary_status);
            $this->assertSame(['BIDDING_STRATEGY_LEARNING'], $campaign->fresh()->google_ads_primary_status_reasons);
        });
    }

    public function test_google_ads_sync_explains_a_google_api_refusal(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            'googleads.googleapis.com/*' => Http::response([[
                'error' => ['message' => 'The customer account cannot be accessed.'],
            ]], 403),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '2005073692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);

            try {
                app(GoogleAdsReportingClient::class)->sync($integration);
                $this->fail('Google Ads refusal should stop the synchronization.');
            } catch (\LogicException $exception) {
                $this->assertSame('Google Ads a refusé la synchronisation : The customer account cannot be accessed.', $exception->getMessage());
            }

            $this->assertSame(
                'Google Ads a refusé la synchronisation : The customer account cannot be accessed.',
                $integration->fresh()->credentials['last_sync_error'],
            );
            $this->assertNotNull($integration->fresh()->credentials['last_sync_failed_at']);
        });
    }

    public function test_scheduled_google_ads_sync_runs_for_each_active_organization_and_records_an_audit_event(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            'googleads.googleapis.com/*' => Http::sequence()
                ->push([['results' => [[
                    'campaign' => ['id' => '123', 'status' => 'ENABLED', 'primaryStatus' => 'ELIGIBLE'],
                ]]]])
                ->push([['results' => [[
                    'campaign' => ['id' => '123', 'status' => 'ENABLED'],
                    'segments' => ['date' => '2026-08-31'],
                    'metrics' => ['costMicros' => '1000000', 'impressions' => '10', 'clicks' => '2', 'conversions' => 1],
                ]]]]),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            Campaign::query()->create([
                'name' => 'Campagne planifiée', 'channel' => 'google_ads', 'tracking_key' => 'scheduled-campaign',
                'external_reference' => '123', 'status' => CampaignStatus::Active, 'currency' => 'EUR',
            ]);
            OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '2005073692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);
        });

        $this->artisan('cremona:sync-google-ads')
            ->expectsOutput("{$organization->name}: 1 journée(s) actualisée(s).")
            ->assertExitCode(0);

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->assertNotNull(OrganizationIntegration::query()->sole()->credentials['last_synced_at']);
            $this->assertNull(OrganizationIntegration::query()->sole()->credentials['last_sync_error']);
            $this->assertSame('google_ads.reporting_synchronized', OrganizationAuditLog::query()->sole()->event);
        });
    }

    public function test_google_ads_publisher_creates_a_complete_campaign_paused(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            '*/campaignBudgets:mutate' => Http::response(['results' => [['resourceName' => 'customers/2005073692/campaignBudgets/1']]]),
            '*/campaigns:mutate' => Http::response(['results' => [['resourceName' => 'customers/2005073692/campaigns/42']]]),
            '*/googleAds:searchStream' => Http::response([['results' => [[
                'geoTargetConstant' => [
                    'resourceName' => 'geoTargetConstants/123', 'name' => 'Rhone',
                    'countryCode' => 'FR', 'status' => 'ENABLED',
                ],
            ]]]]),
            '*/campaignCriteria:mutate' => Http::response(['results' => []]),
            '*/adGroups:mutate' => Http::response(['results' => [['resourceName' => 'customers/2005073692/adGroups/7']]]),
            '*/adGroupCriteria:mutate' => Http::response(['results' => []]),
            '*/adGroupAds:mutate' => Http::response(['results' => []]),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '2005073692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Recherche', 'channel' => 'google_ads', 'tracking_key' => 'atelier-archets', 'status' => CampaignStatus::Draft, 'currency' => 'EUR',
                'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'planned_budget' => 100,
                'configuration' => ['budget_mode' => 'total', 'conversion_goal' => 'generate_lead', 'final_url' => 'https://atelierivoincidit.fr/contact', 'target_locations' => 'Rhône', 'languages' => 'fr', 'ad_groups' => [['name' => 'Archets', 'keywords' => '"archet violon"', 'negative_keywords' => 'occasion', 'headlines' => "Archets artisanaux\nEssayer un archet\nConseil d’archetier", 'descriptions' => "Découvrez les archets de l’atelier.\nEssayez-les avec votre instrument."]]],
            ]);

            app(GoogleAdsCampaignPublisher::class)->publishPaused($campaign, $integration);

            $campaign->refresh();
            $this->assertSame('42', $campaign->external_reference);
            $this->assertSame(CampaignStatus::Paused, $campaign->status);
        });

        Http::assertSentCount(9);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/googleAds:searchStream')
            && str_contains($request['query'], "geo_target_constant.name = 'Rhone'"));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/adGroupCriteria:mutate')
            && $request['operations'][0]['create']['keyword'] === ['text' => 'archet violon', 'matchType' => 'PHRASE']);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/campaigns:mutate')
            && array_key_exists('targetSpend', $request['operations'][0]['create'])
            && $request['operations'][0]['create']['containsEuPoliticalAdvertising'] === 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING'
            && $request['operations'][0]['create']['startDateTime'] === '20260901 00:00:00'
            && $request['operations'][0]['create']['endDateTime'] === '20260930 23:59:59');
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/campaignBudgets:mutate')
            && $request['operations'][0]['create']['period'] === 'CUSTOM_PERIOD'
            && $request['operations'][0]['create']['totalAmountMicros'] === 100000000);
    }

    public function test_google_ads_publisher_removes_the_budget_when_campaign_creation_fails(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'short-lived-token']);
            }
            if (str_ends_with($request->url(), '/googleAds:searchStream')) {
                return Http::response([['results' => [[
                    'geoTargetConstant' => [
                        'resourceName' => 'geoTargetConstants/123', 'name' => 'Rhone',
                        'countryCode' => 'FR', 'status' => 'ENABLED',
                    ],
                ]]]]);
            }
            if (str_ends_with($request->url(), '/campaignBudgets:mutate')) {
                return Http::response(['results' => [['resourceName' => 'customers/2005073692/campaignBudgets/1']]]);
            }
            if (str_ends_with($request->url(), '/campaigns:mutate') && isset($request['operations'][0]['create'])) {
                return Http::response(['error' => ['message' => 'Invalid JSON payload']], 400);
            }

            return Http::response(['results' => []]);
        });
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '2005073692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Recherche', 'channel' => 'google_ads', 'tracking_key' => 'atelier-archets', 'status' => CampaignStatus::Draft, 'currency' => 'EUR',
                'configuration' => ['conversion_goal' => 'generate_lead', 'final_url' => 'https://atelierivoincidit.fr/contact', 'daily_budget' => 15, 'target_locations' => 'Rhône', 'languages' => 'fr', 'ad_groups' => [['name' => 'Archets', 'keywords' => 'archet violon', 'negative_keywords' => 'occasion', 'headlines' => "Archets artisanaux\nEssayer un archet\nConseil d’archetier", 'descriptions' => "Découvrez les archets de l’atelier.\nEssayez-les avec votre instrument."]]],
            ]);

            $this->expectException(\Illuminate\Http\Client\RequestException::class);
            app(GoogleAdsCampaignPublisher::class)->publishPaused($campaign, $integration);
        });

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/campaignBudgets:mutate')
            && $request['operations'] === [['remove' => 'customers/2005073692/campaignBudgets/1']]);
    }

    public function test_google_ads_publisher_activates_only_a_campaign_created_paused(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            '*/googleAds:searchStream' => Http::sequence()
                ->push([['results' => [['adGroup' => ['resourceName' => 'customers/2005073692/adGroups/7']]]]])
                ->push([['results' => [['adGroupAd' => ['resourceName' => 'customers/2005073692/adGroupAds/7~9']]]]]),
            '*/adGroups:mutate' => Http::response(['results' => []]),
            '*/adGroupAds:mutate' => Http::response(['results' => []]),
            '*/campaigns:mutate' => Http::response(['results' => [['resourceName' => 'customers/2005073692/campaigns/42']]]),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '200-507-3692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Recherche', 'channel' => 'google_ads', 'tracking_key' => 'atelier-archets', 'external_reference' => '42', 'status' => CampaignStatus::Paused, 'currency' => 'EUR',
            ]);

            app(GoogleAdsCampaignPublisher::class)->activate($campaign, $integration);

            $this->assertSame(CampaignStatus::Active, $campaign->refresh()->status);
        });

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/campaigns:mutate')) {
                return false;
            }

            return $request['operations'][0] === [
                'updateMask' => 'status',
                'update' => [
                    'resourceName' => 'customers/2005073692/campaigns/42',
                    'status' => 'ENABLED',
                ],
            ];
        });
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/adGroups:mutate')
            && $request['operations'] === [[
                'updateMask' => 'status',
                'update' => ['resourceName' => 'customers/2005073692/adGroups/7', 'status' => 'ENABLED'],
            ]]);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/adGroupAds:mutate')
            && $request['operations'] === [[
                'updateMask' => 'status',
                'update' => ['resourceName' => 'customers/2005073692/adGroupAds/7~9', 'status' => 'ENABLED'],
            ]]);
    }

    public function test_google_ads_publisher_removes_an_unlaunched_paused_campaign_and_keeps_the_local_draft(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            '*/campaigns:mutate' => Http::response(['results' => []]),
        ]);
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'google_ads', 'name' => 'reporting', 'status' => 'active',
                'credentials' => ['customer_id' => '200-507-3692', 'developer_token' => 'developer-token', 'oauth_client_id' => 'client-id', 'oauth_client_secret' => 'client-secret', 'refresh_token' => 'refresh-token'],
            ]);
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Recherche', 'channel' => 'google_ads', 'tracking_key' => 'atelier-archets', 'external_reference' => '42', 'status' => CampaignStatus::Paused, 'currency' => 'EUR',
                'configuration' => ['ad_groups' => [['name' => 'Archets']]],
            ]);

            app(GoogleAdsCampaignPublisher::class)->discardPaused($campaign, $integration);

            $campaign->refresh();
            $this->assertNull($campaign->external_reference);
            $this->assertSame(CampaignStatus::Draft, $campaign->status);
            $this->assertSame('Archets', $campaign->configuration['ad_groups'][0]['name']);
        });

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/campaigns:mutate')
            && $request['operations'] === [['remove' => 'customers/2005073692/campaigns/42']]);
    }
}
