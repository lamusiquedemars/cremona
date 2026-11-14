<?php

namespace App\Filament\Resources\AppointmentTypes\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\AppointmentTypes\AppointmentTypeResource;

class EditAppointmentType extends BusinessEditRecord
{
    protected static string $resource = AppointmentTypeResource::class;
}
