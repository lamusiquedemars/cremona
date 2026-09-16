<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessViewRecord extends ViewRecord
{
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    public function getBreadcrumb(): string
    {
        return $this->getRecordTitle();
    }
}
