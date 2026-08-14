<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Model;

abstract class CrmPolicy
{
    public function viewAny(User $user): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    public function view(User $user, Model $record): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null
            && (int) $record->getAttribute('organization_id') === $organization->getKey()
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->view($user, $record) && $this->canManage($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    protected function canManage(User $user): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ManageCrm, $organization);
    }
}
