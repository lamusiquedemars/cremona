<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\EmailMailbox;
use App\Models\User;
use App\Tenancy\OrganizationContext;

class EmailMailboxPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, EmailMailbox $mailbox): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null
            && $mailbox->organization_id === $organization->getKey()
            && $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, EmailMailbox $mailbox): bool
    {
        return $this->view($user, $mailbox);
    }

    public function delete(User $user, EmailMailbox $mailbox): bool
    {
        return $this->view($user, $mailbox);
    }

    private function canManage(User $user): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ManageEmailMailboxes, $organization);
    }
}
