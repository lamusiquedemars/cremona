<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessEditRecord extends EditRecord
{
    public function getTitle(): string|Htmlable
    {
        return 'Modifier · '.$this->getRecordTitle();
    }

    public function getBreadcrumb(): string
    {
        return $this->getRecordTitle();
    }
}
