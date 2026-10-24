<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\Appointments\AppointmentResource;

class CreateAppointment extends BusinessCreateRecord
{
    protected static string $resource = AppointmentResource::class;
}
