<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Pages\BusinessCreateClientRecord;
use App\Filament\Resources\People\PersonResource;

class CreatePerson extends BusinessCreateClientRecord
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
