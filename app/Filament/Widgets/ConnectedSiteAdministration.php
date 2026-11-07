<?php

namespace App\Filament\Widgets;

use App\Services\ConnectedSiteAdministration as ConnectedSiteAdministrationService;
use App\Tenancy\OrganizationContext;
use Filament\Widgets\Widget;

class ConnectedSiteAdministration extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.connected-site-administration';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();

        return app(ConnectedSiteAdministrationService::class)->urlFor($organization) !== null;
    }

    protected function getViewData(): array
    {
        $organization = app(OrganizationContext::class)->require();

        return [
            'url' => app(ConnectedSiteAdministrationService::class)->urlFor($organization),
        ];
    }
}
