<?php

namespace App\Filament\Concerns;

use App\Enums\OrganizationPermission;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;

trait UsesOrganizationConfiguration
{
    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && static::canManageOrganizationConfiguration();
    }

    public static function canAccess(): bool
    {
        return parent::canAccess()
            && static::canManageOrganizationConfiguration();
    }

    protected static function canManageOrganizationConfiguration(): bool
    {
        $user = auth()->user();
        $organization = app(OrganizationContext::class)->current();

        return $user instanceof User
            && $organization !== null
            && static::organizationModuleIsEnabled()
            && $user->hasOrganizationPermission(static::configurationPermission(), $organization);
    }

    protected static function organizationModuleIsEnabled(): bool
    {
        return ! property_exists(static::class, 'organizationModule')
            || app(OrganizationModuleAccess::class)->enabled(static::$organizationModule);
    }

    protected static function configurationPermission(): OrganizationPermission
    {
        return OrganizationPermission::ManageIntegrations;
    }
}
