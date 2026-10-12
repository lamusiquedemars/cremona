<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Concerns\ManagesPrimaryContactDetails;
use App\Filament\Resources\People\PersonResource;

class EditPerson extends BusinessEditRecord
{
    use ManagesPrimaryContactDetails;

    protected static string $resource = PersonResource::class;

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
