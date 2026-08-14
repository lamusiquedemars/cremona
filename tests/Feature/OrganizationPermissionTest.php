<?php

namespace Tests\Feature;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_grant_their_default_permissions(): void
    {
        $organization = Organization::factory()->create();
        $administrator = User::factory()->create();
        $viewer = User::factory()->create();

        $administrator->organizations()->attach($organization, [
            'role' => OrganizationRole::Administrator->value,
        ]);
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);

        $this->assertTrue($administrator->hasOrganizationPermission(
            OrganizationPermission::ManageIntegrations,
            $organization,
        ));
        $this->assertFalse($viewer->hasOrganizationPermission(
            OrganizationPermission::ManageIntegrations,
            $organization,
        ));
        $this->assertTrue($viewer->hasOrganizationPermission(
            OrganizationPermission::ViewCrm,
            $organization,
        ));
        $this->assertFalse($viewer->hasOrganizationPermission(
            OrganizationPermission::ManageCrm,
            $organization,
        ));
    }

    public function test_explicit_overrides_can_grant_and_deny_permissions(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();

        $owner->organizations()->attach($organization, [
            'role' => OrganizationRole::Owner->value,
            'permissions' => ['manage_integrations' => false],
        ]);
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
            'permissions' => ['view_audit_log' => true],
        ]);

        $this->assertFalse($owner->hasOrganizationPermission(
            OrganizationPermission::ManageIntegrations,
            $organization,
        ));
        $this->assertTrue($collaborator->hasOrganizationPermission(
            OrganizationPermission::ViewAuditLog,
            $organization,
        ));
    }

    public function test_platform_administrators_have_every_organization_permission(): void
    {
        $organization = Organization::factory()->create();
        $administrator = User::factory()->platformAdministrator()->create();

        foreach (OrganizationPermission::cases() as $permission) {
            $this->assertTrue($administrator->hasOrganizationPermission($permission, $organization));
        }
    }
}
