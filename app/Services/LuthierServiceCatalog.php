<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Tenancy\OrganizationContext;

class LuthierServiceCatalog
{
    private const DEFAULTS = [
        ['code' => 'REMECHAGE', 'name' => 'Reméchage d’archet', 'description' => 'Reméchage d’archet ; mèche et finition à préciser.'],
        ['code' => 'AME', 'name' => 'Réglage ou remplacement d’âme', 'description' => 'Réglage, pose ou remplacement d’âme selon diagnostic atelier.'],
        ['code' => 'CHEVALET', 'name' => 'Chevalet : fourniture, taille et pose', 'description' => 'Fourniture du chevalet, taille, ajustement et pose.'],
        ['code' => 'MONTAGE_CORDES', 'name' => 'Montage de cordes', 'description' => 'Montage et accordage après remplacement de cordes.'],
        ['code' => 'REVISION', 'name' => 'Révision et entretien', 'description' => 'Révision générale et entretien courant de l’instrument.'],
        ['code' => 'DIAGNOSTIC', 'name' => 'Diagnostic atelier', 'description' => 'Diagnostic et estimation de l’intervention avant accord.'],
    ];

    public function seed(Organization $organization): void
    {
        app(OrganizationContext::class)->run($organization, function (): void {
            foreach (self::DEFAULTS as $service) {
                ServiceDefinition::query()->firstOrCreate(['code' => $service['code']], [...$service, 'suggested_unit_amount' => 0, 'tax_rate' => 0, 'is_active' => true]);
            }
        });
    }
}
