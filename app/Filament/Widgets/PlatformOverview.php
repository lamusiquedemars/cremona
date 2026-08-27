<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Vue globale Cremona';

    protected ?string $description = 'Administration transversale des organisations et de leurs sites.';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->is_platform_admin ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Organisations actives', Organization::query()->where('status', 'active')->count())
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('primary')
                ->url(OrganizationResource::getUrl('index')),
            Stat::make('Sites rattachés', OrganizationSite::query()->where('status', 'active')->count())
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('info'),
            Stat::make('Comptes utilisateurs', User::query()->count())
                ->icon(Heroicon::OutlinedUsers)
                ->color('gray'),
            Stat::make('Campagnes actives', Campaign::withoutGlobalScopes()->where('status', 'active')->count())
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('success'),
        ];
    }
}
