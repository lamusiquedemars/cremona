<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Concerns\ManagesPrimaryContactDetails;
use App\Filament\Resources\People\PersonResource;

class CreatePerson extends BusinessCreateRecord
{
    use ManagesPrimaryContactDetails;

    protected static string $resource = PersonResource::class;

    public function getTitle(): string
    {
        return 'Nouveau client particulier';
    }

    public function getBreadcrumb(): string
    {
        return 'Nouveau client particulier';
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
