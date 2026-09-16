<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessCreateRecord extends CreateRecord
{
    public function getTitle(): string|Htmlable
    {
        return 'Nouveau '.static::getResource()::getModelLabel();
    }

    public function getBreadcrumb(): string
    {
        return 'Nouveau';
    }
}
