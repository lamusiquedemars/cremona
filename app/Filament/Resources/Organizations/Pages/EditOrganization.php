<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Services\OrganizationModuleRegistry;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    /** @var array<int, string> */
    private array $modules = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['modules'] = app(OrganizationModuleRegistry::class)->selectionFor($this->record);
        $data['vertical_pack'] ??= '__none__';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->modules = app(OrganizationModuleRegistry::class)->selectedFromSelection($data['modules'] ?? []);
        unset($data['modules']);
        $data['vertical_pack'] = ($data['vertical_pack'] ?? null) === 'luthier' ? 'luthier' : null;

        return $data;
    }

    protected function afterSave(): void
    {
        app(OrganizationModuleRegistry::class)->syncForPack($this->record, $this->record->vertical_pack, $this->modules);
    }
}
