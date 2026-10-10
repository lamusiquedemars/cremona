<?php

namespace App\Filament\Concerns;

use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationPresentation;
use UnitEnum;

trait UsesOrganizationPresentation
{
    public static function getNavigationLabel(): string
    {
        return app(OrganizationPresentation::class)->navigationLabel(static::$presentationKey ?? '', static::$navigationLabel ?? parent::getNavigationLabel());
    }

    public static function getModelLabel(): string
    {
        return static::$modelLabel ?? parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? parent::getPluralModelLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::$navigationGroup;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && app(OrganizationModuleAccess::class)->enabled(static::$organizationModule);
    }

    public static function canAccess(): bool
    {
        return parent::canAccess()
            && app(OrganizationModuleAccess::class)->enabled(static::$organizationModule);
    }
}
