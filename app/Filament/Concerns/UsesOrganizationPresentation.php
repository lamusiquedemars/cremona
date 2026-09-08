<?php

namespace App\Filament\Concerns;

use App\Services\OrganizationPresentation;
use UnitEnum;

trait UsesOrganizationPresentation
{
    public static function getNavigationLabel(): string
    {
        return app(OrganizationPresentation::class)->label(static::$presentationKey ?? '', static::$navigationLabel ?? parent::getNavigationLabel());
    }

    public static function getModelLabel(): string
    {
        return app(OrganizationPresentation::class)->label(static::$presentationKey ?? '', static::$modelLabel ?? parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return app(OrganizationPresentation::class)->label(static::$presentationKey ?? '', static::$pluralModelLabel ?? parent::getPluralModelLabel());
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = static::$navigationGroup;

        return is_string($group)
            ? app(OrganizationPresentation::class)->label(static::$presentationGroupKey ?? '', $group)
            : $group;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && app(OrganizationPresentation::class)->isVisible(static::$presentationKey ?? '');
    }
}
