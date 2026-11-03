<?php

namespace App\Services;

use App\Models\AppointmentType;
use App\Models\Organization;
use App\Tenancy\OrganizationContext;

class DemoMaracujaAppointmentTypeCatalog
{
    /** @var array<int, array{label: string, description: string, default_duration_minutes: int}> */
    private const DEFAULTS = [
        ['label' => 'Parlons de votre projet web', 'description' => 'Premier échange pour comprendre le projet, son objectif et ses contraintes.', 'default_duration_minutes' => 30],
        ['label' => 'Diagnostic de votre présence numérique', 'description' => 'Analyse de la présence en ligne et des priorités d’amélioration.', 'default_duration_minutes' => 45],
        ['label' => 'Démonstration de l’espace d’administration', 'description' => 'Présentation de l’administration et des parcours utiles à l’équipe.', 'default_duration_minutes' => 30],
        ['label' => 'Rendez-vous de suivi', 'description' => 'Point d’avancement avec un client existant.', 'default_duration_minutes' => 30],
        ['label' => 'Assistance sur votre site', 'description' => 'Aide ciblée sur l’utilisation ou l’évolution du site.', 'default_duration_minutes' => 20],
    ];

    public function seed(Organization $organization): int
    {
        return app(OrganizationContext::class)->run($organization, function (): int {
            $created = 0;
            foreach (self::DEFAULTS as $default) {
                $type = AppointmentType::query()->firstOrCreate(['label' => $default['label']], $default + ['is_active' => true]);
                $created += $type->wasRecentlyCreated ? 1 : 0;
            }

            return $created;
        });
    }
}
