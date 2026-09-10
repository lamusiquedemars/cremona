<?php

namespace App\Services;

use App\Tenancy\OrganizationContext;

class OrganizationPresentation
{
    /** @var array<string, array{label: string, items: array<string, string>}> */
    private const GROUPS = [
        'customer_follow_up' => [
            'label' => 'Suivi client',
            'items' => [
                'contacts' => 'Contacts',
                'companies' => 'Entreprises',
                'requests' => 'Demandes',
                'conversations' => 'Correspondances',
                'quotes' => 'Devis',
            ],
        ],
        'commercial_activity' => [
            'label' => 'Activité commerciale',
            'items' => [
                'tasks' => 'Tâches',
                'appointments' => 'Rendez-vous',
                'documents' => 'Documents',
            ],
        ],
        'workshop' => [
            'label' => 'Atelier',
            'items' => [],
        ],
        'marketing' => [
            'label' => 'Marketing',
            'items' => [
                'campaigns' => 'Campagnes',
            ],
        ],
    ];

    /** @var array<string, true> */
    private const KEYS = [
        'customer_follow_up' => true,
        'commercial_activity' => true,
        'workshop' => true,
        'catalog_inventory' => true,
        'marketing' => true,
        'communications' => true,
        'contacts' => true,
        'companies' => true,
        'requests' => true,
        'conversations' => true,
        'tasks' => true,
        'appointments' => true,
        'quotes' => true,
        'documents' => true,
        'campaigns' => true,
        'instruments' => true,
        'interventions' => true,
        'rentals' => true,
        'inventory' => true,
        'contempo' => true,
    ];

    public function label(string $key, string $fallback): string
    {
        if (! isset(self::KEYS[$key])) {
            return $fallback;
        }

        $value = data_get(app(OrganizationContext::class)->current()?->settings ?? [], "presentation.labels.{$key}");

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    public function createActionLabel(string $key, string $fallback): string
    {
        return 'Créer : '.$this->label($key, $fallback);
    }

    /** @return array<string, array{label: string, items: array<string, string>}> */
    public static function groups(): array
    {
        return self::GROUPS;
    }
}
