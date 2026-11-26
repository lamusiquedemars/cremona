<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Pages\BusinessEditClientRecord;
use App\Filament\Resources\People\PersonResource;
use App\Filament\RelationManagers\ClientSearchesRelationManager;

class EditPerson extends BusinessEditClientRecord
{
    protected static string $resource = PersonResource::class;

    public function getRelationManagers(): array
    {
        return [ClientSearchesRelationManager::class];
    }
}
