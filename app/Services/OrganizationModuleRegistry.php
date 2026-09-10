<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Tenancy\OrganizationContext;

final class OrganizationModuleRegistry
{
    /** @return array<string, array{label: string, description: string, group: string}> */
    public function all(): array
    {
        return [
            'contacts' => ['label' => 'Contacts et entreprises', 'description' => 'Fiches contacts et entreprises.', 'group' => 'Relation client'],
            'inquiries' => ['label' => 'Demandes', 'description' => 'Demandes entrantes et canaux du site.', 'group' => 'Relation client'],
            'conversations' => ['label' => 'Correspondances', 'description' => 'Emails et échanges avec les contacts.', 'group' => 'Relation client'],
            'quotes' => ['label' => 'Devis', 'description' => 'Propositions commerciales et lignes enregistrées.', 'group' => 'Relation client'],
            'appointments' => ['label' => 'Rendez-vous', 'description' => 'Rendez-vous et connexion Brevo Meetings.', 'group' => 'Organisation'],
            'tasks' => ['label' => 'Tâches', 'description' => 'Tâches et échéances.', 'group' => 'Organisation'],
            'documents' => ['label' => 'Documents', 'description' => 'Documents privés associés au suivi.', 'group' => 'Organisation'],
            'acquisition' => ['label' => 'Acquisition', 'description' => 'Campagnes et connexion Google Ads.', 'group' => 'Acquisition'],
            'instruments' => ['label' => 'Instruments', 'description' => 'Parc d’instruments et publication contrôlée.', 'group' => 'Offre'],
            'interventions' => ['label' => 'Atelier', 'description' => 'Dossiers atelier et prestations.', 'group' => 'Offre'],
            'rentals' => ['label' => 'Locations', 'description' => 'Contrats et suivi des locations.', 'group' => 'Offre'],
            'inventory' => ['label' => 'Stock', 'description' => 'Articles, niveaux et mouvements de stock.', 'group' => 'Offre'],
            'contempo' => ['label' => 'Projection Contempo', 'description' => 'Projection publique contrôlée des instruments.', 'group' => 'Offre'],
        ];
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return collect($this->all())->mapWithKeys(
            fn (array $definition, string $module): array => [$module => $definition['group'].' — '.$definition['label']],
        )->all();
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
