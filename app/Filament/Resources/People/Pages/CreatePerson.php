<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\People\PersonResource;

class CreatePerson extends BusinessCreateRecord
{
    protected static string $resource = PersonResource::class;

    public function getTitle(): string
    {
        return 'Nouveau client particulier';
    }

    public function getBreadcrumb(): string
    {
        return 'Nouveau client particulier';
    }
}
