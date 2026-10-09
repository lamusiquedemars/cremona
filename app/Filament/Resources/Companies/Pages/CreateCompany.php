<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\Companies\CompanyResource;

class CreateCompany extends BusinessCreateRecord
{
    protected static string $resource = CompanyResource::class;

    public function getTitle(): string
    {
        return 'Nouvelle entreprise';
    }

    public function getBreadcrumb(): string
    {
        return 'Nouvelle entreprise';
    }
}
