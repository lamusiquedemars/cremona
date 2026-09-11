<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Tenancy\OrganizationContext;

final class OrganizationModuleRegistry
{
    /** @var array<int, string> */
    private const PACK_PRESETS = [
        '__none__' => [
            'modules' => ['crm', 'appointments', 'quotes', 'marketing', 'communications'],
            'labels' => [
                'customer_follow_up' => 'Relation client',
                'crm' => 'Contacts, demandes et échanges',
                'commercial_activity' => 'Activité commerciale',
                'quotes' => 'Devis et documents',
                'marketing' => 'Campagnes et publicité',
                'communications' => 'Boîtes email et connecteurs',
            ],
        ],
        'luthier' => [
            'modules' => ['crm', 'appointments', 'quotes', 'luthier_catalog', 'workshop', 'rentals', 'inventory', 'communications'],
            'labels' => [
                'customer_follow_up' => 'Clients',
                'crm' => 'Contacts, demandes et échanges',
                'commercial_activity' => 'Activité commerciale',
                'quotes' => 'Devis et documents',
                'workshop' => 'Atelier',
                'luthier_catalog' => 'Instruments et prestations',
                'rentals' => 'Locations',
                'catalog_inventory' => 'Stock et accessoires',
                'inventory' => 'Articles et stock',
                'communications' => 'Boîtes email et connecteurs',
            ],
        ],
    ];

    /** @return array<string, array{label: string, description: string, group: string, group_key: string, presentation_key: string, requires: array<int, string>}> */
    public function all(): array
    {
        return [
            'crm' => ['label' => 'Contacts, demandes et échanges', 'description' => 'Contacts, entreprises, demandes, correspondances et tâches.', 'group' => 'Suivi client', 'group_key' => 'customer_follow_up', 'presentation_key' => 'crm', 'requires' => []],
            'appointments' => ['label' => 'Rendez-vous', 'description' => 'Rendez-vous et agenda.', 'group' => 'Suivi client', 'group_key' => 'customer_follow_up', 'presentation_key' => 'appointments', 'requires' => []],
            'quotes' => ['label' => 'Devis et documents', 'description' => 'Devis, lignes de devis enregistrées et documents privés.', 'group' => 'Activité commerciale', 'group_key' => 'commercial_activity', 'presentation_key' => 'quotes', 'requires' => []],
            'luthier_catalog' => ['label' => 'Catalogue luthier', 'description' => 'Instruments et prestations atelier.', 'group' => 'Atelier', 'group_key' => 'workshop', 'presentation_key' => 'luthier_catalog', 'requires' => []],
            'workshop' => ['label' => 'Dossiers atelier', 'description' => 'Suivi des interventions sur les instruments. Requiert le catalogue luthier.', 'group' => 'Atelier', 'group_key' => 'workshop', 'presentation_key' => 'workshop', 'requires' => ['luthier_catalog']],
            'rentals' => ['label' => 'Locations', 'description' => 'Contrats et suivi des locations. Requiert le catalogue luthier.', 'group' => 'Atelier', 'group_key' => 'workshop', 'presentation_key' => 'rentals', 'requires' => ['luthier_catalog']],
            'inventory' => ['label' => 'Articles et stock', 'description' => 'Articles et niveaux de stock.', 'group' => 'Catalogue et stock', 'group_key' => 'catalog_inventory', 'presentation_key' => 'inventory', 'requires' => []],
            'marketing' => ['label' => 'Campagnes et publicité', 'description' => 'Campagnes et publicité Google.', 'group' => 'Marketing', 'group_key' => 'marketing', 'presentation_key' => 'marketing', 'requires' => []],
            'communications' => ['label' => 'Boîtes email et connecteurs', 'description' => 'Canaux entrants, boîtes email, Brevo Meetings et connecteurs de publication.', 'group' => 'Canaux et intégrations', 'group_key' => 'communications', 'presentation_key' => 'communications', 'requires' => []],
        ];
    }

    /** @return array<string, array{label: string, modules: array<string, array{label: string, description: string, group: string, group_key: string, presentation_key: string, requires: array<int, string>}>}> */
    public function grouped(): array
    {
        return collect($this->all())
            ->groupBy('group_key', preserveKeys: true)
            ->map(fn ($definitions): array => [
                'label' => $definitions->first()['group'],
                'modules' => $definitions->all(),
            ])
            ->all();
    }

    /** @return array<int, string> */
    public function enabledFor(Organization $organization): array
    {
        return OrganizationModule::withoutGlobalScopes()
            ->where('organization_id', $organization->getKey())
            ->where('enabled', true)
            ->whereIn('module', array_keys($this->all()))
            ->pluck('module')
            ->all();
    }

    /** @return array<string, bool> */
    public function selectionFor(Organization $organization): array
    {
        $enabled = array_flip($this->enabledFor($organization));

        return collect(array_keys($this->all()))
            ->mapWithKeys(fn (string $module): array => [$module => isset($enabled[$module])])
            ->all();
    }

    /** @param array<string, mixed> $selection @return array<int, string> */
    public function selectedFromSelection(array $selection): array
    {
        return $this->withDependencies(
            collect($selection)
                ->filter(fn (mixed $enabled): bool => $enabled === true)
                ->keys()
                ->intersect(array_keys($this->all()))
                ->values()
                ->all(),
        );
    }

    /** @param array<int, string> $modules @return array<int, string> */
    public function withDependencies(array $modules): array
    {
        $selected = array_fill_keys(array_intersect(array_keys($this->all()), $modules), true);

        do {
            $added = false;

            foreach (array_keys($selected) as $module) {
                foreach ($this->all()[$module]['requires'] as $requiredModule) {
                    if (! isset($selected[$requiredModule])) {
                        $selected[$requiredModule] = true;
                        $added = true;
                    }
                }
            }
        } while ($added);

        return array_keys($selected);
    }

    /** @param array<int, string> $modules @return array<int, string> */
    public function forPack(?string $verticalPack, array $modules): array
    {
        return $this->withDependencies($modules);
    }

    /** @return array{modules: array<int, string>, labels: array<string, string>} */
    public function preset(?string $verticalPack): array
    {
        return self::PACK_PRESETS[$verticalPack === 'luthier' ? 'luthier' : '__none__'];
    }

    /** @param array<int, string> $modules */
    public function sync(Organization $organization, array $modules): void
    {
        $knownModules = array_keys($this->all());
        $selectedModules = $this->withDependencies($modules);

        app(OrganizationContext::class)->run($organization, function () use ($organization, $knownModules, $selectedModules): void {
            foreach ($knownModules as $module) {
                OrganizationModule::withoutGlobalScopes()->updateOrCreate(
                    ['organization_id' => $organization->getKey(), 'module' => $module],
                    ['enabled' => in_array($module, $selectedModules, true)],
                );
            }
        });
    }

    /** @param array<int, string> $modules */
    public function syncForPack(Organization $organization, ?string $verticalPack, array $modules): void
    {
        $this->sync($organization, $this->forPack($verticalPack, $modules));
    }
}
