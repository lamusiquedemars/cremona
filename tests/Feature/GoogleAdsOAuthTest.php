<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAdsOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_authorize_google_ads_without_exposing_refresh_token(): void
    {
        $organization = Organization::factory()->create();
        $administrator = User::factory()->create();
        $administrator->organizations()->attach($organization, ['role' => OrganizationRole::Administrator->value]);

        $integration = app(OrganizationContext::class)->run($organization, fn (): OrganizationIntegration => app(OrganizationIntegrationManager::class)->configure(
            'google_ads', 'reporting', [
                'customer_id' => '2005073692',
                'oauth_client_id' => 'client-id.apps.googleusercontent.com',
                'oauth_client_secret' => 'client-secret',
            ], $administrator,
        ));

        $this->actingAs($administrator)
            ->get(route('google-ads.oauth.authorize', $integration))
            ->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth')
            ->assertSessionHas('google_ads_oauth.state');

        Http::fake(['oauth2.googleapis.com/token' => Http::response(['refresh_token' => 'secret-refresh-token'])]);

        $this->actingAs($administrator)
            ->withSession(['google_ads_oauth' => [
                'state' => 'secure-state',
                'integration_id' => $integration->getKey(),
                'user_id' => $administrator->getKey(),
            ]])
            ->get(route('google-ads.oauth.callback', ['code' => 'short-code', 'state' => 'secure-state']))
            ->assertRedirect('/dashboard/'.$organization->slug);

        $updated = OrganizationIntegration::withoutGlobalScopes()->findOrFail($integration->getKey());
        $this->assertSame('secret-refresh-token', $updated->credentials['refresh_token']);
    }

    public function test_central_api_credentials_are_used_without_being_copied_to_the_organization(): void
    {
        Config::set('services.google_ads', [
            'developer_token' => 'central-developer-token',
            'oauth_client_id' => 'central-client-id',
            'oauth_client_secret' => 'central-client-secret',
            'login_customer_id' => '9998887776',
        ]);
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);

        $integration = app(OrganizationContext::class)->run($organization, fn (): OrganizationIntegration => app(OrganizationIntegrationManager::class)->configure(
            'google_ads', 'reporting', ['customer_id' => '2005073692'], $owner,
        ));

        $this->actingAs($owner)
            ->get(route('google-ads.oauth.authorize', $integration))
            ->assertRedirectContains('client_id=central-client-id');

        Http::fake(['oauth2.googleapis.com/token' => Http::response(['refresh_token' => 'organization-refresh-token'])]);

        $this->actingAs($owner)
            ->withSession(['google_ads_oauth' => [
                'state' => 'secure-state',
                'integration_id' => $integration->getKey(),
                'user_id' => $owner->getKey(),
            ]])
            ->get(route('google-ads.oauth.callback', ['code' => 'short-code', 'state' => 'secure-state']))
            ->assertRedirect('/dashboard/'.$organization->slug);

        $credentials = OrganizationIntegration::withoutGlobalScopes()->findOrFail($integration->getKey())->credentials;
        $this->assertSame(['customer_id' => '2005073692', 'refresh_token' => 'organization-refresh-token'], $credentials);
    }

    public function test_oauth_client_rejection_returns_to_the_organization_without_a_server_error(): void
    {
        Config::set('services.google_ads', [
            'oauth_client_id' => 'central-client-id',
            'oauth_client_secret' => 'wrong-secret',
        ]);
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);

        $integration = app(OrganizationContext::class)->run($organization, fn (): OrganizationIntegration => app(OrganizationIntegrationManager::class)->configure(
            'google_ads', 'reporting', ['customer_id' => '2005073692'], $owner,
        ));
        Http::fake(['oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_client',
        ], 401)]);

        $this->actingAs($owner)
            ->withSession(['google_ads_oauth' => [
                'state' => 'secure-state',
                'integration_id' => $integration->getKey(),
                'user_id' => $owner->getKey(),
            ]])
            ->get(route('google-ads.oauth.callback', ['code' => 'short-code', 'state' => 'secure-state']))
            ->assertRedirect('/dashboard/'.$organization->slug)
            ->assertSessionHas('error', 'Google a refusé l’autorisation. Vérifiez que le client OAuth et son secret proviennent du même identifiant Google Cloud.');

        $this->assertArrayNotHasKey('refresh_token', OrganizationIntegration::withoutGlobalScopes()->findOrFail($integration->getKey())->credentials);
    }
}
