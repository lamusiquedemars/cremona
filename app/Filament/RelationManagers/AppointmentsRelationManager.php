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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('common.appointment');
    }

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label(__('common.date'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('title')->label(__('common.appointment'))->weight('medium'),
                TextColumn::make('modality')->label(__('common.modality'))->badge(),
                TextColumn::make('status')->label(__('common.status'))->badge(),
                TextColumn::make('assignedUser.name')->label(__('common.assignee'))->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record]));
    }
}
