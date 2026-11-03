<?php

namespace App\Filament\Resources\AppointmentTypes\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\AppointmentTypes\AppointmentTypeResource;

class CreateAppointmentType extends BusinessCreateRecord
{
    protected static string $resource = AppointmentTypeResource::class;
}
