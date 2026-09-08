<?php

namespace App\Filament\Concerns;

use App\Services\OrganizationPresentation;
use UnitEnum;

trait UsesOrganizationPresentation
{
    public static function getNavigationLabel(): string
    {
        return app(OrganizationPresentation::class)->label(static::$presentationKey ?? '', static::$navigationLabel ?? static::getModelLabel());
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
        return app(OrganizationPresentation::class)->isVisible(static::$presentationKey ?? '');
    }
}
