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
        $data['settings']['timezone'] ??= config('app.timezone', 'UTC');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->modules = app(OrganizationModuleRegistry::class)->selectedFromSelection($data['modules'] ?? []);
        unset($data['modules']);
        $data['vertical_pack'] = filled($data['vertical_pack'] ?? null) ? $data['vertical_pack'] : null;

        return $data;
    }

    protected function afterSave(): void
    {
        app(OrganizationModuleRegistry::class)->sync($this->record, $this->modules);
    }
}
