<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePortalLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_site_proof_closes_the_central_session_and_returns_to_the_fixed_site_url(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->portalClient($organization);

        $state = str_repeat('a', 96);
        $response = $this->actingAs($user)
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => $state,
                'signature' => $this->signature($state),
            ]);

        $response->assertRedirect('https://site.example.test/admin/sso/logout/complete?state='.$state)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest();
    }

    public function test_an_invalid_site_proof_cannot_trigger_a_central_logout(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->portalClient($organization);

        $this->actingAs($user)
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => str_repeat('a', 96),
                'signature' => 'invalid',
            ])
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_valid_site_proof_does_not_depend_on_browser_origin_headers(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->portalClient($organization);

        $this->actingAs($user)
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => str_repeat('a', 96),
                'signature' => $this->signature(str_repeat('a', 96)),
            ])
            ->assertRedirect('https://site.example.test/admin/sso/logout/complete?state='.str_repeat('a', 96));

        $this->assertGuest();
    }

    public function test_a_central_user_without_access_to_the_site_organization_is_not_logged_out_but_the_site_can_finish_its_logout(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $this->portalClient($organization);

        $this->actingAs($user)
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => str_repeat('a', 96),
                'signature' => $this->signature(str_repeat('a', 96)),
            ])
            ->assertRedirect('https://site.example.test/admin/sso/logout/complete?state='.str_repeat('a', 96));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_platform_registered_site_can_complete_a_federated_logout(): void
    {
        $organization = Organization::factory()->create(['slug' => 'contempo']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $organization->sites()->create([
            'name' => 'Contempo',
            'base_url' => 'https://contempo.example.test',
            'status' => 'active',
            'site_portal_enabled' => true,
            'site_portal_client_id' => 'contempo',
            'site_portal_secret' => 'contempo-shared-secret',
            'site_portal_logout_redirect_uri' => 'https://contempo.example.test/admin/sso/logout/complete',
        ]);

        $state = str_repeat('b', 96);
        $this->actingAs($user)
            ->post(route('site-portal.logout'), [
                'client_id' => 'contempo',
                'state' => $state,
                'signature' => hash_hmac('sha256', 'contempo|'.$state, 'contempo-shared-secret'),
            ])
            ->assertRedirect('https://contempo.example.test/admin/sso/logout/complete?state='.$state);

        $this->assertGuest();
    }

    private function portalClient(Organization $organization): void
    {
        config()->set('cremona.site_portal.clients.site-demo-client', [
            'organization' => $organization->slug,
            'site_url' => 'https://site.example.test/admin',
            'logout_redirect_uri' => 'https://site.example.test/admin/sso/logout/complete',
            'logout_secret' => 'test-shared-secret',
        ]);
    }

    private function signature(string $state): string
    {
        return hash_hmac('sha256', 'site-demo-client|'.$state, 'test-shared-secret');
    }
}
