<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Services\OrganizationModuleRegistry;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    /** @var array<int, string> */
    private array $modules = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->modules = app(OrganizationModuleRegistry::class)->selectedFromSelection($data['modules'] ?? []);
        unset($data['modules']);
        $data['vertical_pack'] = filled($data['vertical_pack'] ?? null) ? $data['vertical_pack'] : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(OrganizationModuleRegistry::class)->sync($this->record, $this->modules);
    }
}
