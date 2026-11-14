<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Pages\BusinessEditClientRecord;
use App\Filament\Resources\Companies\CompanyResource;

class EditCompany extends BusinessEditClientRecord
{
    protected static string $resource = CompanyResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }
}
