<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Tenancy\OrganizationContext;

class ConversationMessagePolicy
{
    public function view(User $user, ConversationMessage $message): bool
    {
        return $message->organization_id === app(OrganizationContext::class)->id()
            && $this->can($user, OrganizationPermission::ViewCorrespondence);
    }

    public function create(User $user): bool
    {
        return $this->can($user, OrganizationPermission::ReplyCorrespondence);
    }

    public function link(User $user, ConversationMessage $message): bool
    {
        return $this->view($user, $message)
            && $this->can($user, OrganizationPermission::ManageCorrespondenceLinks);
    }

    private function can(User $user, OrganizationPermission $permission): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return $organization !== null && $user->hasOrganizationPermission($permission, $organization);
    }
}
