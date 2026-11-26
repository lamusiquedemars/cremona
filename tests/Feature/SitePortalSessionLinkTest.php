<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePortalSessionLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_member_can_consume_a_site_ticket_only_once(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site-demo']);
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->legacyClient($organization);

        $entryUrl = $this->requestLink('site-demo-client', $user, 'workspace')
            ->assertOk()
            ->json('entry_url');

        $this->assertIsString($entryUrl);
        $this->get($entryUrl)
            ->assertRedirect(url('/dashboard/site-demo'));
        $this->assertAuthenticatedAs($user);

        // Cache::pull() makes the browser hand-off strictly single use.
        $this->get($entryUrl)->assertUnauthorized();
    }

    public function test_an_expired_or_invalid_site_signature_cannot_issue_a_ticket(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site-demo']);
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->legacyClient($organization);

        $this->requestLink('site-demo-client', $user, 'workspace', time() - 61)
            ->assertUnauthorized();
    }

    public function test_a_ticket_does_not_open_cremona_after_the_member_loses_access(): void
    {
        $organization = Organization::factory()->create(['slug' => 'site-demo']);
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->legacyClient($organization);

        $entryUrl = $this->requestLink('site-demo-client', $user, 'workspace')
            ->assertOk()
            ->json('entry_url');
        $this->assertIsString($entryUrl);

        $user->organizations()->detach($organization);

        $this->get($entryUrl)->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_a_second_registered_site_uses_its_own_organization_and_ticket(): void
    {
        $organization = Organization::factory()->create(['slug' => 'contempo']);
        $user = User::factory()->create(['email' => 'giovanni@contempo.test']);
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $organization->sites()->create([
            'name' => 'Contempo',
            'base_url' => 'https://contempo.example.test',
            'status' => 'active',
            'site_portal_enabled' => true,
            'site_portal_client_id' => 'contempo',
            'site_portal_secret' => 'contempo-shared-secret',
            'site_portal_items' => ['workspace'],
        ]);

        $entryUrl = $this->requestLink('contempo', $user, 'workspace', secret: 'contempo-shared-secret')
            ->assertOk()
            ->json('entry_url');
        $this->assertIsString($entryUrl);

        $this->get($entryUrl)
            ->assertRedirect(url('/dashboard/contempo'));
        $this->assertAuthenticatedAs($user);
    }

    private function legacyClient(Organization $organization): void
    {
        config()->set('cremona.site_portal.clients.site-demo-client', [
            'organization' => $organization->slug,
            'logout_secret' => 'test-shared-secret',
            'items' => ['workspace'],
        ]);
    }

    private function requestLink(string $clientId, User $user, string $item, ?int $timestamp = null, string $secret = 'test-shared-secret')
    {
        $timestamp ??= time();
        $email = strtolower($user->email);
        $subject = (string) $user->getAuthIdentifier();

        return $this->postJson(route('api.v1.site-portal.session-link'), [
            'client_id' => $clientId,
            'subject' => $subject,
            'email' => $email,
            'item' => $item,
            'timestamp' => $timestamp,
            'signature' => hash_hmac('sha256', implode('|', [$clientId, $subject, $email, $item, $timestamp]), $secret),
        ]);
    }
}
