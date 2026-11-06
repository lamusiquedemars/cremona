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

    public function test_an_authorized_site_origin_closes_the_central_session_and_returns_to_the_fixed_site_url(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->portalClient($organization);

        $state = str_repeat('a', 96);
        $response = $this->actingAs($user)
            ->withHeader('Origin', 'https://site.example.test')
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => $state,
            ]);

        $response->assertRedirect('https://site.example.test/admin/sso/logout/complete?state='.$state)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest();
    }

    public function test_an_unexpected_origin_cannot_trigger_a_central_logout(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->portalClient($organization);

        $this->actingAs($user)
            ->withHeader('Origin', 'https://attacker.example.test')
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => str_repeat('a', 96),
            ])
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_safari_referer_fallback_is_limited_to_the_configured_site_origin(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->portalClient($organization);

        $this->actingAs($user)
            ->withHeader('Referer', 'https://site.example.test/admin/sso/logout')
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => str_repeat('a', 96),
            ])
            ->assertRedirect('https://site.example.test/admin/sso/logout/complete?state='.str_repeat('a', 96));

        $this->assertGuest();
    }

    public function test_a_central_user_without_access_to_the_site_organization_is_not_logged_out(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $this->portalClient($organization);

        $this->actingAs($user)
            ->withHeader('Origin', 'https://site.example.test')
            ->post(route('site-portal.logout'), [
                'client_id' => 'site-demo-client',
                'state' => str_repeat('a', 96),
            ])
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    private function portalClient(Organization $organization): void
    {
        config()->set('cremona.site_portal.clients.site-demo-client', [
            'organization' => $organization->slug,
            'site_url' => 'https://site.example.test/admin',
            'logout_redirect_uri' => 'https://site.example.test/admin/sso/logout/complete',
        ]);
    }
}
