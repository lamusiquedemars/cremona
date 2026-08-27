<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Filament\Resources\GoogleAdsConnections\GoogleAdsConnectionResource;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $administrator = User::factory()->create();
        $collaborator = User::factory()->create();
        $administrator->organizations()->attach($organization, ['role' => OrganizationRole::Administrator->value]);
        $collaborator->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        $url = GoogleAdsConnectionResource::getUrl('index', tenant: $organization);

        $this->actingAs($administrator)->get($url)->assertOk()->assertSee('Google Ads');
        $this->actingAs($collaborator)->get($url)->assertForbidden();
    }
}
