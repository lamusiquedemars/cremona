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
