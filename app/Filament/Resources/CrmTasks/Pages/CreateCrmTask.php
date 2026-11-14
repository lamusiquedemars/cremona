<?php

namespace App\Filament\Resources\CrmTasks\Pages;

use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateCrmTask extends BusinessCreateRecord
{
    protected static string $resource = CrmTaskResource::class;
}
