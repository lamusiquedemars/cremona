<?php

namespace App\Filament\Resources\ElectronicReports\Pages;

use App\Filament\Resources\ElectronicReports\ElectronicReportResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewElectronicReport extends ViewRecord
{
    protected static string $resource = ElectronicReportResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'E-reporting — '.($this->record->invoice?->reference ?: '#'.$this->record->id);
    }
}
