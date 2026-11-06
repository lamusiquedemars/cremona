<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\SitePortalOAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Scope;
use Tests\TestCase;

class SitePortalOAuthClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_member_skips_consent_for_the_trusted_site_portal_only(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);

        $client = new SitePortalOAuthClient(['id' => 'trusted-site-client']);
        config()->set('cremona.site_portal.clients.trusted-site-client', [
            'organization' => $organization->slug,
        ]);

        $this->assertTrue($client->skipsAuthorization($user, [new Scope('site:access', 'Portail')]));
        $this->assertFalse($client->skipsAuthorization($user, [new Scope('site:access', 'Portail'), new Scope('other', 'Autre')]));
    }

    public function test_a_non_member_never_skips_consent(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site.maracujadigital.fr']);
        $user = User::factory()->create();
        $client = new SitePortalOAuthClient(['id' => 'trusted-site-client']);
        config()->set('cremona.site_portal.clients.trusted-site-client', [
            'organization' => $organization->slug,
        ]);

        $this->assertFalse($client->skipsAuthorization($user, [new Scope('site:access', 'Portail')]));
    }
}
