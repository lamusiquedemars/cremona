<?php

namespace App\Filament\Resources\CrmTasks\Pages;

use App\Filament\Resources\CrmTasks\CrmTaskResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCrmTask extends ViewRecord
{
    protected static string $resource = CrmTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
