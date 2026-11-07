<?php

namespace App\Services;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

final class SitePortalNavigation
{
    /** @return array<int, array{key: string, label: string, url: string}> */
    public function for(User $user, Organization $organization, array $requestedItems): array
    {
        $definitions = [
            'clients' => ['label' => 'Clients', 'module' => 'crm', 'permission' => OrganizationPermission::ViewCrm, 'path' => 'client-directory'],
            'requests' => ['label' => 'Demandes', 'module' => 'crm', 'permission' => OrganizationPermission::ViewCrm, 'path' => 'incoming-requests'],
            'appointments' => ['label' => 'Rendez-vous', 'module' => 'appointments', 'permission' => OrganizationPermission::ViewCrm, 'path' => 'appointments'],
            'quotes' => ['label' => 'Devis', 'module' => 'quotes', 'permission' => OrganizationPermission::ViewCrm, 'path' => 'quotes'],
            'invoices' => ['label' => 'Facturation', 'module' => 'invoicing', 'permission' => null, 'path' => 'invoices'],
            // Les opérations du pack Luthier sont filtrées par les mêmes
            // modules et profils que la navigation Cremona. Un site connecté
            // ne peut donc jamais élargir les droits de son membre.
            'instruments' => ['label' => 'Instruments', 'module' => 'luthier_catalog', 'permission' => null, 'path' => 'instrument-assets'],
            'rentals' => ['label' => 'Locations', 'module' => 'rentals', 'permission' => null, 'path' => 'rentals'],
            'workshop_orders' => ['label' => 'Dossiers atelier', 'module' => 'workshop', 'permission' => null, 'path' => 'workshop-orders'],
        ];

        $enabledModules = app(OrganizationModuleRegistry::class)->enabledFor($organization);

        return collect($requestedItems)
            ->map(function (string $key) use ($definitions): ?array {
                $item = $definitions[$key] ?? null;

                return $item === null ? null : ['key' => $key, ...$item];
            })
            ->filter()
            ->filter(fn (array $item): bool => in_array($item['module'], $enabledModules, true))
            ->filter(fn (array $item): bool => $user->canAccessOrganizationModule($item['module'], $organization))
            ->filter(fn (array $item): bool => $item['permission'] === null || $user->hasOrganizationPermission($item['permission'], $organization))
            ->map(fn (array $item): array => [
                'key' => $item['key'],
                'label' => $item['label'],
                'url' => url('/dashboard/'.$organization->slug.'/'.$item['path']),
            ])
            ->values()
            ->all();
    }
}
