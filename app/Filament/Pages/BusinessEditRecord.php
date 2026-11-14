<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasContextualRecordTitle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessEditRecord extends EditRecord
{
    use HasContextualRecordTitle;

    public function getTitle(): string|Htmlable
    {
        return $this->contextualRecordTitle();
    }

    public function getBreadcrumb(): string
    {
        return 'Modifier';
    }
}
