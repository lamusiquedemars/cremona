<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsReportingClient;
use App\Services\GoogleAdsCampaignPublisher;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                    'campaign' => ['id' => '123', 'name' => 'Defesa penal', 'status' => 'ENABLED'],
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
        });

        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v25/customers/2005073692/googleAds:searchStream')
            && $request->hasHeader('developer-token', 'developer-token')
            && $request->hasHeader('Authorization', 'Bearer short-lived-token'));
    }

    public function test_google_ads_publisher_creates_a_complete_campaign_paused(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
            '*/campaignBudgets:mutate' => Http::response(['results' => [['resourceName' => 'customers/2005073692/campaignBudgets/1']]]),
            '*/campaigns:mutate' => Http::response(['results' => [['resourceName' => 'customers/2005073692/campaigns/42']]]),
            '*/geoTargetConstants:suggest' => Http::response(['geoTargetConstantSuggestions' => [[
                'searchTerm' => 'Rhône',
                'geoTargetConstant' => [
                    'resourceName' => 'geoTargetConstants/123', 'name' => 'Rhône',
                    'countryCode' => 'FR', 'status' => 'ENABLED',
                ],
            ]]]),
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
                'configuration' => ['conversion_goal' => 'generate_lead', 'final_url' => 'https://atelierivoincidit.fr/contact', 'daily_budget' => 15, 'target_locations' => 'Rhône', 'languages' => 'fr', 'ad_groups' => [['name' => 'Archets', 'keywords' => '"archet violon"', 'negative_keywords' => 'occasion', 'headlines' => "Archets artisanaux\nEssayer un archet\nConseil d’archetier", 'descriptions' => "Découvrez les archets de l’atelier.\nEssayez-les avec votre instrument."]]],
            ]);

            app(GoogleAdsCampaignPublisher::class)->publishPaused($campaign, $integration);

            $campaign->refresh();
            $this->assertSame('42', $campaign->external_reference);
            $this->assertSame(CampaignStatus::Paused, $campaign->status);
        });

        Http::assertSentCount(8);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/geoTargetConstants:suggest'));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/adGroupCriteria:mutate')
            && $request['operations'][0]['create']['keyword'] === ['text' => 'archet violon', 'matchType' => 'PHRASE']);
    }

    public function test_google_ads_publisher_activates_only_a_campaign_created_paused(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'short-lived-token']),
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
    }
}
