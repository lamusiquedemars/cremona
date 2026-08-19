<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Conversation;
use App\Models\User;
use App\Tenancy\OrganizationContext;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, OrganizationPermission::ViewCorrespondence);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->organization_id === app(OrganizationContext::class)->id()
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, OrganizationPermission::ReplyCorrespondence);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $this->can($user, OrganizationPermission::ReplyCorrespondence);
    }

    public function link(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $this->can($user, OrganizationPermission::ManageCorrespondenceLinks);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return false;
    }

    private function can(User $user, OrganizationPermission $permission): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null && $user->hasOrganizationPermission($permission, $organization);
    }
}
