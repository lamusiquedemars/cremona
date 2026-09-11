<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrganizationRole;
use App\Filament\Widgets\ActiveCampaigns;
use App\Filament\Widgets\DashboardHomeSummary;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Policies\CampaignPolicy;
use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationModuleRegistry;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class OrganizationModuleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_selection_is_saved_per_organization(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleRegistry::class)->sync($organization, [
            'crm' => true,
            'marketing' => true,
        ]);

        $this->assertSame([
            'crm' => true,
            'appointments' => false,
            'quotes' => false,
            'marketing' => true,
        ], app(OrganizationModuleRegistry::class)->selectionFor($organization));
        $this->assertTrue(app(OrganizationModuleAccess::class)->enabled('marketing', $organization));
        $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('quotes', $organization));
    }

    public function test_active_campaign_card_includes_impressions_and_clicks(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->actingAs($user);

        app(OrganizationModuleRegistry::class)->sync($organization, ['marketing' => true]);

        app(OrganizationContext::class)->run($organization, function (): void {
            $campaign = Campaign::query()->create([
                'name' => 'Campagne directe',
                'channel' => 'google_ads',
                'tracking_key' => 'directe',
                'status' => CampaignStatus::Active,
                'currency' => 'EUR',
            ]);
            $campaign->dailyMetrics()->create([
                'metric_date' => now()->toDateString(),
                'spend' => 42.50,
                'impressions' => 1200,
                'clicks' => 36,
                'currency' => 'EUR',
                'source' => 'manual',
            ]);
        });

        app(OrganizationContext::class)->set($organization);
        Filament::setTenant($organization, isQuiet: true);

        $this->assertTrue(ActiveCampaigns::canView());

        $method = new ReflectionMethod(ActiveCampaigns::class, 'getViewData');
        $data = $method->invoke(app(ActiveCampaigns::class));

        $this->assertMatchesRegularExpression('/1.?200/u', $data['campaigns'][0]['impressions']);
        $this->assertSame('36', $data['campaigns'][0]['clicks']);
        $this->assertStringContainsString('42,50', $data['campaigns'][0]['spend']);
    }

    public function test_home_summary_remains_visible_when_there_is_no_action_to_process(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['name' => 'Marcos Túlio']);
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->actingAs($user);
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm' => true]);

        app(OrganizationContext::class)->run($organization, fn () => Person::query()->create([
            'display_name' => 'Cliente test',
        ]));
        app(OrganizationContext::class)->set($organization);

        $method = new ReflectionMethod(DashboardHomeSummary::class, 'getViewData');
        $data = $method->invoke(app(DashboardHomeSummary::class));

        $this->assertSame('Marcos', $data['first_name']);
        $this->assertSame('Aucune action urgente aujourd’hui.', $data['status']);
        $this->assertSame('1 contact · 0 demande en 30 j', $data['overview'][0]['value']);
    }

    public function test_campaign_preparation_is_reserved_for_platform_administrators(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $administrator = User::factory()->platformAdministrator()->create();
        $owner->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $campaign = app(OrganizationContext::class)->run($organization, fn (): Campaign => Campaign::query()->create([
            'name' => 'Campagne protégée',
            'channel' => 'google_ads',
            'tracking_key' => 'protegee',
            'status' => CampaignStatus::Active,
        ]));
        app(OrganizationContext::class)->set($organization);

        $policy = new CampaignPolicy;

        $this->assertFalse($policy->update($owner, $campaign));
        $this->assertTrue($policy->update($administrator, $campaign));
    }
}
