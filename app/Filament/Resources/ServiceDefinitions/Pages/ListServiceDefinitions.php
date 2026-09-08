<?php

namespace App\Filament\Resources\ServiceDefinitions\Pages;

use App\Filament\Resources\ServiceDefinitions\ServiceDefinitionResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceDefinitions extends ListRecords
{
    protected static string $resource = ServiceDefinitionResource::class;
}
