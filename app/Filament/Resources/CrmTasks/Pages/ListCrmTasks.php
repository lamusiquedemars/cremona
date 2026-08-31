<?php

namespace App\Filament\Resources\CrmTasks\Pages;

use App\Filament\Resources\CrmTasks\CrmTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListCrmTasks extends ListRecords
{
    protected static string $resource = CrmTaskResource::class;
}
