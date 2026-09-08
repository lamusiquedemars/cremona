<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Tenancy\OrganizationContext;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    public function getTabs(): array
    {
        $now = now();
        $endOfDay = now(app(OrganizationContext::class)->require()->timezone())->endOfDay()->utc();

        return [
            'all' => Tab::make('Tous'),
            'today' => Tab::make('Aujourd’hui')
                ->badge(fn (): int => Appointment::query()
                    ->where('status', AppointmentStatus::Scheduled)
                    ->whereBetween('starts_at', [$now, $endOfDay])
                    ->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', AppointmentStatus::Scheduled)
                    ->whereBetween('starts_at', [$now, $endOfDay])),
            'upcoming' => Tab::make('À venir')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', AppointmentStatus::Scheduled)
                    ->where('starts_at', '>=', $now)),
            'past' => Tab::make('Passés')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('starts_at', '<', $now)),
        ];
    }
}
