<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Tenancy\OrganizationContext;

final class OrganizationModuleRegistry
{
    /** @return array<string, array{label: string, description: string}> */
    public function all(): array
    {
        return [
            'crm' => ['label' => 'Suivi client', 'description' => 'Contacts, entreprises, demandes, correspondances et tâches.'],
            'appointments' => ['label' => 'Rendez-vous', 'description' => 'Agenda et rendez-vous.'],
            'quotes' => ['label' => 'Devis', 'description' => 'Devis et suivi commercial.'],
            'marketing' => ['label' => 'Acquisition', 'description' => 'Campagnes et données Google Ads.'],
        ];
    }

    /** @return array<string, bool> */
    public function selectionFor(Organization $organization): array
    {
        $enabled = OrganizationModule::withoutGlobalScopes()
            ->where('organization_id', $organization->getKey())
            ->where('enabled', true)
            ->whereIn('module', array_keys($this->all()))
            ->pluck('module')
            ->flip();

        return collect(array_keys($this->all()))
            ->mapWithKeys(fn (string $module): array => [$module => $enabled->has($module)])
            ->all();
    }

    /** @param array<string, mixed> $selection */
    public function sync(Organization $organization, array $selection): void
    {
        $modules = array_keys($this->all());

        app(OrganizationContext::class)->run($organization, function () use ($organization, $selection, $modules): void {
            foreach ($modules as $module) {
                OrganizationModule::withoutGlobalScopes()->updateOrCreate(
                    ['organization_id' => $organization->getKey(), 'module' => $module],
                    ['enabled' => filter_var($selection[$module] ?? false, FILTER_VALIDATE_BOOLEAN)],
                );
            }
        });
    }
}
