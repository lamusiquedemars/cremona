<?php

namespace App\Filament\Concerns;

use App\Services\OrganizationModuleAccess;

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

    protected static function organizationModuleIsEnabled(): bool
    {
        return app(OrganizationModuleAccess::class)->enabled(static::$organizationModule);
    }
}
