<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Services\OrganizationModuleRegistry;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    /** @var array<string, mixed> */
    private array $modules = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->modules = $data['modules'] ?? [];
        unset($data['modules']);

        return $data;
    }

    protected function afterCreate(): void
    {
        app(OrganizationModuleRegistry::class)->sync($this->record, $this->modules);
    }
}
