<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\PrivateDocument;
use App\Models\User;
use App\Support\PrivateDocumentPolicyCatalog;
use Illuminate\Database\Eloquent\Model;

class PrivateDocumentPolicy extends CrmPolicy
{
    public function view(User $user, Model $record): bool
    {
        if (! $record instanceof PrivateDocument || ! parent::view($user, $record)) {
            return false;
        }

        $organization = $record->organization;
        $rule = app(PrivateDocumentPolicyCatalog::class)->ruleFor($organization, $record->category);

        return $rule['access'] !== 'restricted'
            || $user->hasOrganizationPermission(OrganizationPermission::ManageIntegrations, $organization);
    }
}
