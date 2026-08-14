<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_only_access_their_organizations(): void
    {
        $member = User::factory()->create();
        $ownOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $member->organizations()->attach($ownOrganization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        $panel = Filament::getPanel('admin');

        $this->assertTrue($member->canAccessPanel($panel));
        $this->assertTrue($member->canAccessTenant($ownOrganization));
        $this->assertFalse($member->canAccessTenant($otherOrganization));
        $this->assertSame([$ownOrganization->id], $member->getTenants($panel)->pluck('id')->all());
    }

    public function test_a_platform_administrator_can_access_every_active_organization(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        $active = Organization::factory()->create();
        $suspended = Organization::factory()->create(['status' => 'suspended']);

        $panel = Filament::getPanel('admin');

        $this->assertTrue($administrator->canAccessTenant($active));
        $this->assertFalse($administrator->canAccessTenant($suspended));
        $this->assertSame([$active->id], $administrator->getTenants($panel)->pluck('id')->all());
    }

    public function test_a_user_without_an_organization_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }
}
