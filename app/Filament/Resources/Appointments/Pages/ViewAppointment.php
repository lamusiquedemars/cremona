<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Pages\BusinessViewRecord;
use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\EditAction;

class ViewAppointment extends BusinessViewRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Modifier')
                ->visible(fn (): bool => $this->record->provider === 'manual'),
        ];
    }
}
