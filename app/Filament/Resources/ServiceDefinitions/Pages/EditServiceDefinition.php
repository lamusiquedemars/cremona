<?php

namespace App\Filament\Resources\ServiceDefinitions\Pages;

use App\Filament\Resources\ServiceDefinitions\ServiceDefinitionResource;
use App\Filament\Pages\BusinessEditRecord;

class EditServiceDefinition extends BusinessEditRecord
{
    protected static string $resource = ServiceDefinitionResource::class;
}
