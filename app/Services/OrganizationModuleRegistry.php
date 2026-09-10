<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Tenancy\OrganizationContext;

final class OrganizationModuleRegistry
{
    /** @return array<string, array{label: string, description: string, group: string, group_key: string, presentation_key: string}> */
    public function all(): array
    {
        return [
            'contacts' => ['label' => 'Contacts et entreprises', 'description' => 'Fiches contacts et entreprises.', 'group' => 'Relation client', 'group_key' => 'relation_client', 'presentation_key' => 'contacts'],
            'inquiries' => ['label' => 'Demandes', 'description' => 'Demandes entrantes et canaux du site.', 'group' => 'Relation client', 'group_key' => 'relation_client', 'presentation_key' => 'requests'],
            'conversations' => ['label' => 'Correspondances', 'description' => 'Emails et échanges avec les contacts.', 'group' => 'Relation client', 'group_key' => 'relation_client', 'presentation_key' => 'conversations'],
            'quotes' => ['label' => 'Devis', 'description' => 'Propositions commerciales et lignes enregistrées.', 'group' => 'Relation client', 'group_key' => 'relation_client', 'presentation_key' => 'quotes'],
            'appointments' => ['label' => 'Rendez-vous', 'description' => 'Rendez-vous et connexion Brevo Meetings.', 'group' => 'Organisation', 'group_key' => 'organisation', 'presentation_key' => 'appointments'],
            'tasks' => ['label' => 'Tâches', 'description' => 'Tâches et échéances.', 'group' => 'Organisation', 'group_key' => 'organisation', 'presentation_key' => 'tasks'],
            'documents' => ['label' => 'Documents', 'description' => 'Documents privés associés au suivi.', 'group' => 'Organisation', 'group_key' => 'organisation', 'presentation_key' => 'documents'],
            'acquisition' => ['label' => 'Acquisition', 'description' => 'Campagnes et connexion Google Ads.', 'group' => 'Acquisition', 'group_key' => 'acquisition', 'presentation_key' => 'campaigns'],
            'instruments' => ['label' => 'Instruments', 'description' => 'Parc d’instruments et publication contrôlée.', 'group' => 'Offre', 'group_key' => 'offer', 'presentation_key' => 'instruments'],
            'interventions' => ['label' => 'Atelier', 'description' => 'Dossiers atelier et prestations.', 'group' => 'Offre', 'group_key' => 'offer', 'presentation_key' => 'interventions'],
            'rentals' => ['label' => 'Locations', 'description' => 'Contrats et suivi des locations.', 'group' => 'Offre', 'group_key' => 'offer', 'presentation_key' => 'rentals'],
            'inventory' => ['label' => 'Stock', 'description' => 'Articles, niveaux et mouvements de stock.', 'group' => 'Offre', 'group_key' => 'offer', 'presentation_key' => 'inventory'],
            'contempo' => ['label' => 'Connecteurs de publication', 'description' => 'Connexions de publication contrôlée vers les sites.', 'group' => 'Offre', 'group_key' => 'offer', 'presentation_key' => 'contempo'],
        ];
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return collect($this->all())->mapWithKeys(
            fn (array $definition, string $module): array => [$module => $definition['group'].' — '.$definition['label']],
        )->all();
    }

    /** @return array<string, array{label: string, modules: array<string, array{label: string, description: string, group: string, group_key: string, presentation_key: string}>}> */
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
        return collect($selection)
            ->filter(fn (mixed $enabled): bool => $enabled === true)
            ->keys()
            ->intersect(array_keys($this->all()))
            ->values()
            ->all();
    }

    /** @param array<int, string> $modules */
    public function sync(Organization $organization, array $modules): void
    {
        $knownModules = array_keys($this->all());
        $selectedModules = array_values(array_intersect($knownModules, $modules));

        app(OrganizationContext::class)->run($organization, function () use ($organization, $knownModules, $selectedModules): void {
            foreach ($knownModules as $module) {
                OrganizationModule::withoutGlobalScopes()->updateOrCreate(
                    ['organization_id' => $organization->getKey(), 'module' => $module],
                    ['enabled' => in_array($module, $selectedModules, true)],
                );
            }
        });
    }
}
