<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\People\PersonResource;

class EditPerson extends BusinessEditRecord
{
    protected static string $resource = PersonResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }
}
