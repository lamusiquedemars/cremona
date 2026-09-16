<?php

namespace App\Filament\Resources\CrmTasks\Pages;

use App\Filament\Resources\CrmTasks\CrmTaskResource;
use Filament\Actions\DeleteAction;
use App\Filament\Pages\BusinessEditRecord;

class EditCrmTask extends BusinessEditRecord
{
    protected static string $resource = CrmTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
