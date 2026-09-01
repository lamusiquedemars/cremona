<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Filament\Platform\Pages\GoogleAdsInfrastructure;
use App\Filament\Resources\GoogleAdsConnections\GoogleAdsConnectionResource;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleAdsConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_ads_account_preparation_uses_the_encrypted_integration_vault(): void
    {
        $organization = Organization::factory()->create();
        $administrator = User::factory()->create();
        $administrator->organizations()->attach($organization, ['role' => OrganizationRole::Administrator->value]);

        $integration = app(OrganizationContext::class)->run($organization, function () use ($administrator): OrganizationIntegration {
            return app(OrganizationIntegrationManager::class)->configure('google_ads', 'reporting', [
                'customer_id' => '2005073692',
                'developer_token' => 'secret-developer-token',
                'oauth_client_id' => 'client-id.apps.googleusercontent.com',
                'oauth_client_secret' => 'secret-client',
                'refresh_token' => 'secret-refresh-token',
            ], $administrator);
        });

        $stored = (string) DB::table('organization_integrations')->where('id', $integration->id)->value('credentials');

        $this->assertStringNotContainsString('secret-developer-token', $stored);
        $this->assertStringNotContainsString('secret-refresh-token', $stored);
        $this->assertSame('2005073692', $integration->credentials['customer_id']);
        $this->assertTrue(GoogleAdsConnectionResource::isReady($integration->credentials));
    }

    public function test_only_integration_managers_can_open_google_ads_setup(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $administrator = User::factory()->create();
        $collaborator = User::factory()->create();
        $viewer = User::factory()->create();
        $owner->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $administrator->organizations()->attach($organization, ['role' => OrganizationRole::Administrator->value]);
        $collaborator->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);
        $viewer->organizations()->attach($organization, ['role' => OrganizationRole::Viewer->value]);

        $url = GoogleAdsConnectionResource::getUrl('index', tenant: $organization);

        $this->assertSame('Publicité', GoogleAdsConnectionResource::getNavigationLabel());
        $this->assertSame('Configuration de l’organisation', GoogleAdsConnectionResource::getNavigationGroup());
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Compte Google Ads');
        $this->actingAs($administrator)->get($url)->assertOk()->assertSee('Compte Google Ads');
        $this->actingAs($collaborator)->get($url)->assertForbidden();
        $this->actingAs($viewer)->get($url)->assertForbidden();

        app(OrganizationContext::class)->run($organization, function () use ($owner, $administrator, $collaborator, $viewer): void {
            $this->actingAs($owner);
            $this->assertTrue(GoogleAdsConnectionResource::canViewAny());
            $this->actingAs($administrator);
            $this->assertTrue(GoogleAdsConnectionResource::canViewAny());
            $this->actingAs($collaborator);
            $this->assertFalse(GoogleAdsConnectionResource::canViewAny());
            $this->actingAs($viewer);
            $this->assertFalse(GoogleAdsConnectionResource::canViewAny());
        });
    }

    public function test_organization_screen_never_renders_google_ads_secrets(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);

        app(OrganizationContext::class)->run($organization, fn (): OrganizationIntegration => app(OrganizationIntegrationManager::class)->configure(
            'google_ads', 'reporting', [
                'customer_id' => '2005073692',
                'login_customer_id' => '9998887776',
                'developer_token' => 'never-render-developer-token',
                'oauth_client_id' => 'never-render-client-id',
                'oauth_client_secret' => 'never-render-client-secret',
                'refresh_token' => 'never-render-refresh-token',
            ], $owner,
        ));

        $this->actingAs($owner)
            ->get(GoogleAdsConnectionResource::getUrl('index', tenant: $organization))
            ->assertOk()
            ->assertSee('200-507-3692')
            ->assertSee('Infrastructure technique gérée par Maracuja')
            ->assertDontSee('999-888-7776')
            ->assertDontSee('never-render-developer-token')
            ->assertDontSee('never-render-client-id')
            ->assertDontSee('never-render-client-secret')
            ->assertDontSee('never-render-refresh-token')
            ->assertDontSee('Developer token Google Ads')
            ->assertDontSee('OAuth client secret')
            ->assertDontSee('OAuth refresh token');
    }

    public function test_only_platform_admin_can_view_central_infrastructure_status_without_secret_values(): void
    {
        Config::set('services.google_ads', [
            'developer_token' => 'central-developer-token-never-render',
            'oauth_client_id' => 'central-client-id-never-render',
            'oauth_client_secret' => 'central-client-secret-never-render',
            'login_customer_id' => null,
            'api_access_level' => 'basic',
        ]);
        $platformAdministrator = User::factory()->platformAdministrator()->create();
        $regularUser = User::factory()->create();
        $url = GoogleAdsInfrastructure::getUrl(panel: 'platform');

        $this->actingAs($platformAdministrator)->get($url)
            ->assertOk()
            ->assertSee('Configuration centrale active')
            ->assertDontSee('central-developer-token-never-render')
            ->assertDontSee('central-client-id-never-render')
            ->assertDontSee('central-client-secret-never-render');
        $this->actingAs($regularUser)->get($url)->assertForbidden();
    }

    public function test_connection_state_does_not_treat_a_test_or_pending_api_as_production_ready(): void
    {
        Config::set('services.google_ads', [
            'developer_token' => 'test-token',
            'oauth_client_id' => 'client-id',
            'oauth_client_secret' => 'client-secret',
            'api_access_level' => 'test',
        ]);

        $credentials = ['customer_id' => '2005073692', 'refresh_token' => 'refresh-token'];
        $service = app(\App\Services\GoogleAdsCredentials::class);

        $this->assertFalse($service->isReady($credentials));
        $this->assertSame('Accès API Google en attente', $service->connectionState($credentials));

        Config::set('services.google_ads.api_access_level', 'basic');
        $this->assertTrue($service->isReady($credentials));
        $this->assertSame('Connecté', $service->connectionState($credentials));
    }
}
