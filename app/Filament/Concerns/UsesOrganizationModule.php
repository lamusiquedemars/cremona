<?php

namespace App\Filament\Concerns;

use App\Services\OrganizationModuleAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait UsesOrganizationModule
{
    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && static::organizationModuleIsEnabled();
    }

    public static function canAccess(): bool
    {
        return parent::canAccess()
            && static::organizationModuleIsEnabled();
    }

    public static function canCreate(): bool
    {
        return parent::canCreate() && static::userCanManageOrganizationModule();
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && static::userCanManageOrganizationModule();
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && static::userCanManageOrganizationModule();
    }

    public static function canDeleteAny(): bool
    {
        return parent::canDeleteAny() && static::userCanManageOrganizationModule();
    }

    protected static function organizationModuleIsEnabled(): bool
    {
        return static::userCanAccessOrganizationModule();
    }

    private static function userCanAccessOrganizationModule(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(OrganizationModuleAccess::class)->accessibleBy($user, static::$organizationModule);
    }

    private static function userCanManageOrganizationModule(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(OrganizationModuleAccess::class)->manageableBy($user, static::$organizationModule);
    }
}
