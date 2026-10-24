<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\Appointments\AppointmentResource;

class EditAppointment extends BusinessEditRecord
{
    protected static string $resource = AppointmentResource::class;
}
