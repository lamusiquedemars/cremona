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
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->is_platform_admin ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('cremona.platform.active_organizations'), Organization::query()->where('status', 'active')->count())
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('primary')
                ->url(OrganizationResource::getUrl('index')),
            Stat::make(__('cremona.platform.linked_sites'), OrganizationSite::query()->where('status', 'active')->count())
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('info'),
            Stat::make(__('cremona.platform.user_accounts'), User::query()->count())
                ->icon(Heroicon::OutlinedUsers)
                ->color('gray'),
            Stat::make(__('cremona.platform.active_campaigns'), Campaign::withoutGlobalScopes()->where('status', 'active')->count())
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('success'),
        ];
    }

    protected function getHeading(): ?string
    {
        return __('cremona.platform.overview');
    }

    protected function getDescription(): ?string
    {
        return __('cremona.platform.overview_description');
    }
}
