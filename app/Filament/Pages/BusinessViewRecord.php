<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasContextualRecordTitle;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessViewRecord extends ViewRecord
{
    use HasContextualRecordTitle;

    public function getTitle(): string|Htmlable
    {
        return $this->contextualRecordTitle();
    }

    public function getBreadcrumb(): string
    {
        return 'Voir';
    }
}
