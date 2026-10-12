<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Pages\BusinessEditClientRecord;
use App\Filament\Resources\People\PersonResource;

class EditPerson extends BusinessEditClientRecord
{
    protected static string $resource = PersonResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }
}
