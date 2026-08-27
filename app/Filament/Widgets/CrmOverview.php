<?php

namespace App\Filament\Widgets;

use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\IncomingRequest;
use App\Tenancy\OrganizationContext;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrmOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 10;

    protected ?string $heading = 'Relation client';

    protected ?string $description = 'Les demandes qui nécessitent votre attention.';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    protected function getStats(): array
    {
        $active = fn () => IncomingRequest::query()
            ->where('status', '!=', IncomingRequestStatus::Closed);

        $new = IncomingRequest::query()
            ->where('status', IncomingRequestStatus::New)
            ->count();
        $unread = $active()->whereNull('read_at')->count();
        $mine = $active()->where('assigned_user_id', auth()->id())->count();
        $unassigned = $active()->whereNull('assigned_user_id')->count();
        $waiting = IncomingRequest::query()
            ->where('status', IncomingRequestStatus::WaitingCustomer)
            ->count();

        return [
            Stat::make('Nouvelles demandes', $new)
                ->description("{$unread} non lue".($unread > 1 ? 's' : ''))
                ->descriptionIcon(Heroicon::OutlinedEnvelopeOpen)
                ->color('info')
                ->url(IncomingRequestResource::getUrl('index', ['tab' => 'new'])),
            Stat::make('Mes demandes actives', $mine)
                ->description('Attribuées à vous')
                ->descriptionIcon(Heroicon::OutlinedUserCircle)
                ->color('primary')
                ->url(IncomingRequestResource::getUrl('index', ['tab' => 'mine'])),
            Stat::make('Non attribuées', $unassigned)
                ->description('À répartir dans l’équipe')
                ->descriptionIcon(Heroicon::OutlinedUserMinus)
                ->color($unassigned > 0 ? 'warning' : 'success')
                ->url(IncomingRequestResource::getUrl('index', ['tab' => 'unassigned'])),
            Stat::make('En attente du contact', $waiting)
                ->description('Une relance peut être nécessaire')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('gray')
                ->url(IncomingRequestResource::getUrl('index', ['tab' => 'waiting'])),
        ];
    }
}
