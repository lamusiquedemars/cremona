<?php

namespace App\Filament\Concerns;

use App\Services\OrganizationModuleAccess;
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
        return parent::shouldRegisterNavigation() && static::moduleIsEnabled();
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && static::moduleIsEnabled();
    }

    private static function moduleIsEnabled(): bool
    {
        $module = property_exists(static::class, 'organizationModule')
            ? static::$organizationModule
            : match (static::$presentationKey ?? null) {
                'contacts', 'companies', 'requests', 'conversations', 'tasks' => 'crm',
                'appointments' => 'appointments',
                'quotes', 'documents' => 'quotes',
                'campaigns' => 'marketing',
                'instruments' => 'luthier_catalog',
                'interventions' => 'workshop',
                'rentals' => 'rentals',
                'inventory' => 'inventory',
                default => null,
            };

        return $module === null || app(OrganizationModuleAccess::class)->enabled($module);
    }
}
