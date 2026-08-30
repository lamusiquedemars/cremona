<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrganizationRole;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use App\Services\IncomingRequestManager;
use App\Services\GoogleAdsCampaignDraft;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_costs_and_attributed_requests_stay_inside_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $campaign = app(OrganizationContext::class)->run($organization, function (): Campaign {
            $campaign = Campaign::query()->create([
                'name' => 'Defesa penal Cuiabá',
                'channel' => 'google_ads',
                'tracking_key' => 'criminal-cuiaba',
                'status' => CampaignStatus::Active,
                'planned_budget' => 1500,
                'currency' => 'BRL',
            ]);
            $campaign->dailyMetrics()->create([
                'metric_date' => now()->toDateString(),
                'spend' => 125.50,
                'impressions' => 1200,
                'clicks' => 42,
                'source' => 'manual',
                'currency' => 'BRL',
            ]);
            app(IncomingRequestManager::class)->receive([
                'attribution_campaign' => 'criminal-cuiaba',
                'message' => 'Demande liée à la campagne.',
            ]);

            return $campaign;
        });

        app(OrganizationContext::class)->run($otherOrganization, fn () => Campaign::query()->create([
            'name' => 'Autre campagne',
            'channel' => 'google_ads',
            'tracking_key' => 'criminal-cuiaba',
            'status' => CampaignStatus::Active,
        ]));

        app(OrganizationContext::class)->run($organization, function () use ($campaign): void {
            $campaign->refresh();
            $this->assertSame(1, Campaign::query()->count());
            $this->assertEquals(125.50, $campaign->dailyMetrics()->sum('spend'));
            $this->assertCount(1, $campaign->attributedIncomingRequests()->get());
        });
    }

    public function test_a_crm_collaborator_can_open_the_campaign_workspace(): void
    {
        $organization = Organization::factory()->create();
        $collaborator = User::factory()->create();
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);
        app(OrganizationContext::class)->run($organization, fn () => Campaign::query()->create([
            'name' => 'Campagne visible',
            'channel' => 'google_ads',
            'tracking_key' => 'visible-campaign',
            'status' => CampaignStatus::Active,
        ]));

        $this->actingAs($collaborator)
            ->get(CampaignResource::getUrl('index', tenant: $organization))
            ->assertOk()
            ->assertSee('Campagnes')
            ->assertSee('Campagne visible');

        $this->actingAs($collaborator)
            ->get(Filament::getPanel('admin')->getUrl($organization))
            ->assertOk()
            ->assertSee('Pilotage des campagnes');
    }

    public function test_site_summary_api_returns_only_aggregated_campaign_data(): void
    {
        $organization = Organization::factory()->create();
        $token = app(OrganizationContext::class)->run(
            $organization,
            fn (): string => app(OrganizationIntegrationManager::class)
                ->createApiToken('maracuja_cms', 'Marcos Tulio')['token'],
        );

        app(OrganizationContext::class)->run($organization, function (): void {
            $campaign = Campaign::query()->create([
                'name' => 'Defesa penal Cuiabá',
                'channel' => 'google_ads',
                'tracking_key' => 'criminal-cuiaba',
                'site_reference' => 'marcos-tulio-advocacia',
                'status' => CampaignStatus::Active,
            ]);
            $campaign->dailyMetrics()->create([
                'metric_date' => now()->toDateString(),
                'spend' => 80,
                'currency' => 'BRL',
                'source' => 'manual',
            ]);
            app(IncomingRequestManager::class)->receive([
                'source_site_reference' => 'marcos-tulio-advocacia',
                'attribution_campaign' => 'criminal-cuiaba',
                'email_snapshot' => 'private@example.test',
                'message' => 'Conteúdo confidentiel qui ne doit jamais être renvoyé au site.',
            ]);
        });

        $this->withToken($token)
            ->getJson('/api/v1/acquisition/summary?site_reference=marcos-tulio-advocacia')
            ->assertOk()
            ->assertJsonPath('data.spend', 80)
            ->assertJsonPath('data.leads', 1)
            ->assertJsonPath('data.campaigns.0.tracking_key', 'criminal-cuiaba')
            ->assertJsonMissing(['email_snapshot' => 'private@example.test'])
            ->assertJsonMissing(['message' => 'Conteúdo confidentiel qui ne doit jamais être renvoyé au site.']);
    }

    public function test_google_ads_campaign_configuration_is_kept_inside_the_organization(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Recherche',
                'channel' => 'google_ads',
                'tracking_key' => 'atelier-archets',
                'status' => CampaignStatus::Draft,
                'configuration' => [
                    'conversion_goal' => 'generate_lead',
                    'final_url' => 'https://atelierivoincidit.fr/contact',
                    'daily_budget' => 15,
                    'target_locations' => 'Rhône',
                    'languages' => 'fr',
                    'ad_groups' => [['name' => 'Archets', 'keywords' => 'archet violon']],
                ],
            ]);

            $this->assertSame('generate_lead', $campaign->configuration['conversion_goal']);
            $this->assertSame('https://atelierivoincidit.fr/contact', $campaign->configuration['final_url']);
        });
    }

    public function test_google_ads_preview_is_always_paused_and_requires_a_complete_draft(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Recherche',
                'channel' => 'google_ads',
                'tracking_key' => 'atelier-archets',
                'status' => CampaignStatus::Draft,
                'currency' => 'EUR',
                'configuration' => [
                    'conversion_goal' => 'generate_lead',
                    'final_url' => 'https://atelierivoincidit.fr/contact',
                    'daily_budget' => 15,
                    'target_locations' => 'Rhône',
                    'languages' => 'fr',
                    'ad_groups' => [[
                        'name' => 'Archets',
                        'keywords' => "archet violon\narchet artisanal",
                        'negative_keywords' => 'occasion',
                        'headlines' => "Archets artisanaux\nEssayer un archet\nConseil d’archetier",
                        'descriptions' => "Découvrez les archets de l’atelier.\nChoisissez avec votre instrument.",
                    ]],
                ],
            ]);

            $preview = app(GoogleAdsCampaignDraft::class)->preview($campaign);

            $this->assertSame('PAUSED', $preview['campaign']['status']);
            $this->assertSame('https://atelierivoincidit.fr/contact?utm_source=google&utm_medium=cpc&utm_campaign=atelier-archets', $preview['campaign']['final_url']);
            $this->assertSame(['archet violon', 'archet artisanal'], $preview['ad_groups'][0]['keywords']);
            $this->assertSame(['occasion'], $preview['ad_groups'][0]['negative_keywords']);
        });
    }

    public function test_google_ads_bounded_test_requires_dates_and_returns_a_total_budget_preview(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $campaign = Campaign::query()->create([
                'name' => 'Atelier Ivo — Essai',
                'channel' => 'google_ads',
                'tracking_key' => 'atelier-essai',
                'status' => CampaignStatus::Draft,
                'currency' => 'EUR',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-09-30',
                'planned_budget' => 100,
                'configuration' => [
                    'budget_mode' => 'total',
                    'conversion_goal' => 'generate_lead',
                    'final_url' => 'https://atelierivoincidit.fr/essai',
                    'target_locations' => 'Rhône',
                    'languages' => 'fr',
                    'ad_groups' => [[
                        'name' => 'Archets',
                        'keywords' => 'archet violon',
                        'headlines' => "Archets artisanaux\nEssayer un archet\nConseil d’archetier",
                        'descriptions' => "Découvrez les archets de l’atelier.\nChoisissez avec votre instrument.",
                    ]],
                ],
            ]);

            $preview = app(GoogleAdsCampaignDraft::class)->preview($campaign);

            $this->assertSame('total', $preview['campaign']['budget_mode']);
            $this->assertSame(100.0, $preview['campaign']['total_budget']);
            $this->assertSame('2026-09-30', $preview['campaign']['ends_on']);
        });
    }
}
