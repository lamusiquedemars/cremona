<?php

namespace App\Tenancy\Concerns;

use App\Models\User;
use App\Tenancy\OrganizationContext;
use LogicException;

trait ValidatesOrganizationAssignee
{
    public static function bootValidatesOrganizationAssignee(): void
    {
        static::saving(function ($model): void {
            if ($model->assigned_user_id === null) {
                return;
            }

            $organization = app(OrganizationContext::class)->require();
            $user = User::query()->find($model->assigned_user_id);

            if ($user?->is_platform_admin) {
                return;
            }

            if ($user === null || ! $user->organizations()->whereKey($organization)->exists()) {
                throw new LogicException('The assignee is not a member of the active organization.');
            }
        });
    }
}
