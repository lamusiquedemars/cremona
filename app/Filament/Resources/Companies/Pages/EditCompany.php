<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Concerns\ManagesPrimaryContactDetails;
use App\Filament\Resources\Companies\CompanyResource;

class EditCompany extends BusinessEditRecord
{
    use ManagesPrimaryContactDetails;

    protected static string $resource = CompanyResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractPrimaryContactDetails($data);
    }

    protected function afterSave(): void
    {
        $this->syncPrimaryContactDetails();
    }
}
