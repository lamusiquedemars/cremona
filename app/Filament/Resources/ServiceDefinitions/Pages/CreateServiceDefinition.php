<?php

namespace App\Filament\Resources\ServiceDefinitions\Pages;

use App\Filament\Resources\ServiceDefinitions\ServiceDefinitionResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateServiceDefinition extends BusinessCreateRecord
{
    protected static string $resource = ServiceDefinitionResource::class;
}
