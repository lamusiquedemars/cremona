<?php

namespace App\Filament\Concerns;

use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationPresentation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
        $group = static::$navigationGroup;

        return is_string($group)
            ? app(OrganizationPresentation::class)->navigationGroupLabel(static::$presentationGroupKey ?? '', $group)
            : $group;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && static::userCanAccessOrganizationModule();
    }

    public static function canAccess(): bool
    {
        return parent::canAccess()
            && static::userCanAccessOrganizationModule();
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
