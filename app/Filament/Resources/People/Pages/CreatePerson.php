<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreatePerson extends BusinessCreateRecord
{
    protected static string $resource = PersonResource::class;
}
