<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Tenancy\OrganizationContext;

class OrganizationIntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, OrganizationIntegration $integration): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null
            && $integration->organization_id === $organization->getKey()
            && $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, OrganizationIntegration $integration): bool
    {
        return $this->view($user, $integration);
    }

    public function delete(User $user, OrganizationIntegration $integration): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ManageIntegrations, $organization);
    }
}
