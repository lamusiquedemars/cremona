<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Concerns\ManagesPrimaryContactDetails;
use App\Filament\Resources\Companies\CompanyResource;

class CreateCompany extends BusinessCreateRecord
{
    use ManagesPrimaryContactDetails;

    protected static string $resource = CompanyResource::class;

    public function getTitle(): string
    {
        return 'Nouvelle entreprise';
    }

    public function getBreadcrumb(): string
    {
        return 'Nouvelle entreprise';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractPrimaryContactDetails($data);
    }

    protected function afterCreate(): void
    {
        $this->syncPrimaryContactDetails();
    }
}
