<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Pages\BusinessCreateClientRecord;
use App\Filament\Resources\Companies\CompanyResource;

class CreateCompany extends BusinessCreateClientRecord
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
