<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrganizationRole;
use App\Filament\Widgets\ActiveCampaigns;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
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
}
