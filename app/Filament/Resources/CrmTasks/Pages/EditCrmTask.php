<?php

namespace App\Filament\Resources\CrmTasks\Pages;

use App\Filament\Resources\CrmTasks\CrmTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrmTask extends EditRecord
{
    protected static string $resource = CrmTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
