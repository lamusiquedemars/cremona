<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\QuoteLineTemplate;
use App\Tenancy\OrganizationContext;

class LuthierQuoteLineTemplateCatalog
{
    /** @var list<array{code: string, label: string, kind: string, description: string}> */
    private const DEFAULTS = [
        ['code' => 'REMECHAGE', 'label' => 'Reméchage d’archet', 'kind' => 'service', 'description' => 'Reméchage d’archet ; mèche et finition à préciser.'],
        ['code' => 'AME', 'label' => 'Réglage ou remplacement d’âme', 'kind' => 'service', 'description' => 'Réglage, pose ou remplacement d’âme selon diagnostic atelier.'],
        ['code' => 'CHEVALET', 'label' => 'Chevalet : fourniture, taille et pose', 'kind' => 'service', 'description' => 'Fourniture du chevalet, taille, ajustement et pose.'],
        ['code' => 'CORDES', 'label' => 'Jeu de cordes', 'kind' => 'product', 'description' => 'Jeu de cordes ; marque et référence à préciser.'],
        ['code' => 'MONTAGE_CORDES', 'label' => 'Montage de cordes', 'kind' => 'service', 'description' => 'Montage et accordage après remplacement de cordes.'],
        ['code' => 'REVISION', 'label' => 'Révision et entretien', 'kind' => 'service', 'description' => 'Révision générale et entretien courant de l’instrument.'],
        ['code' => 'LOCATION_MENSUELLE', 'label' => 'Location d’instrument — mensualité', 'kind' => 'rental', 'description' => 'Location mensuelle de l’instrument identifié au contrat.'],
        ['code' => 'FRAIS_EXPEDITION', 'label' => 'Expédition / retour', 'kind' => 'fee', 'description' => 'Frais d’expédition ou de retour selon les modalités convenues.'],
        ['code' => 'DIAGNOSTIC', 'label' => 'Diagnostic atelier', 'kind' => 'service', 'description' => 'Diagnostic et estimation de l’intervention avant accord.'],
    ];

    public function seed(Organization $organization): int
    {
        return app(OrganizationContext::class)->run($organization, function (): int {
            $created = 0;

            foreach (self::DEFAULTS as $template) {
                $line = QuoteLineTemplate::query()->firstOrCreate(
                    ['code' => $template['code']],
                    [...$template, 'default_quantity' => 1, 'default_unit_amount' => 0, 'default_tax_rate' => 0, 'is_active' => true],
                );
                $created += $line->wasRecentlyCreated ? 1 : 0;
            }

            return $created;
        });
    }
}
