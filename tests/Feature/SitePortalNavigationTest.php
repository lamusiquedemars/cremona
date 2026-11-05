<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationModuleRegistry;
use App\Services\SitePortalNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_returns_enabled_and_authorized_portal_items(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo-maracuja']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'quotes']);

        $items = app(SitePortalNavigation::class)->for(
            $user,
            $organization,
            ['clients', 'requests', 'appointments', 'quotes'],
        );

        $this->assertSame(['clients', 'requests', 'quotes'], array_column($items, 'key'));
        $this->assertSame('Clients', $items[0]['label']);
        $this->assertSame(url('/dashboard/demo-maracuja/quotes'), $items[2]['url']);
    }

    public function test_a_member_without_crm_permission_receives_no_portal_item(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo-maracuja']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
            'permissions' => ['view_crm' => false],
        ]);
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm']);

        $items = app(SitePortalNavigation::class)->for($user, $organization, ['clients', 'requests']);

        $this->assertSame([], $items);
    }

    public function test_a_member_never_receives_a_module_excluded_from_its_access_profile(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo-maracuja']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
            'permissions' => ['modules' => ['crm']],
        ]);
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'quotes']);

        $items = app(SitePortalNavigation::class)->for($user, $organization, ['clients', 'quotes']);

        $this->assertSame(['clients'], array_column($items, 'key'));
    }
}
