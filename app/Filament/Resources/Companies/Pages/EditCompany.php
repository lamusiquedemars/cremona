<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\Companies\CompanyResource;

class EditCompany extends BusinessEditRecord
{
    protected static string $resource = CompanyResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }
}
