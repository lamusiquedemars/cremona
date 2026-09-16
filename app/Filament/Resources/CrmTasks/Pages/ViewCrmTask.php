<?php

namespace App\Filament\Resources\CrmTasks\Pages;

use App\Filament\Resources\CrmTasks\CrmTaskResource;
use Filament\Actions\EditAction;
use App\Filament\Pages\BusinessViewRecord;

class ViewCrmTask extends BusinessViewRecord
{
    protected static string $resource = CrmTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
