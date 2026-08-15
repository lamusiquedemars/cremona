<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AppointmentsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Rendez-vous';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('title')->label('Rendez-vous')->weight('medium'),
                TextColumn::make('modality')->label('Modalité')->badge(),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('assignedUser.name')->label('Responsable')->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record]));
    }
}
