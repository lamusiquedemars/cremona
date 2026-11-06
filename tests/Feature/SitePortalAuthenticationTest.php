<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_member_can_authenticate_from_its_site_without_a_browser_redirect(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create(['email' => 'contact@example.test', 'password' => 'correct-password']);
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        config()->set('cremona.site_portal.clients.site-demo-client', [
            'organization' => $organization->slug,
            'logout_secret' => 'test-shared-secret',
            'items' => [],
        ]);

        $timestamp = time();
        $this->postJson(route('api.v1.site-portal.authenticate'), [
            'client_id' => 'site-demo-client',
            'email' => 'CONTACT@example.test',
            'subject' => 'site-user-42',
            'timestamp' => $timestamp,
            'signature' => hash_hmac('sha256', 'site-demo-client|site-user-42|contact@example.test|'.$timestamp, 'test-shared-secret'),
        ])->assertOk()->assertJsonPath('email', 'contact@example.test');
    }
}
