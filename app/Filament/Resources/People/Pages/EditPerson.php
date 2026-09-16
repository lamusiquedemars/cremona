<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Filament\Pages\BusinessEditRecord;

class EditPerson extends BusinessEditRecord
{
    protected static string $resource = PersonResource::class;
}
