<?php

namespace App\Services;

use App\Tenancy\OrganizationContext;

class OrganizationPresentation
{
    /** @var array<string, array{label: string, items: array<string, string>}> */
    private const GROUPS = [
        'relation_client' => [
            'label' => 'Relation client',
            'items' => [
                'contacts' => 'Contacts',
                'companies' => 'Entreprises',
                'requests' => 'Demandes',
                'conversations' => 'Correspondances',
                'tasks' => 'Tâches',
                'appointments' => 'Rendez-vous',
                'quotes' => 'Devis',
                'documents' => 'Documents',
            ],
        ],
        'acquisition' => [
            'label' => 'Acquisition',
            'items' => [
                'campaigns' => 'Campagnes',
            ],
        ],
    ];

    /** @var array<string, true> */
    private const KEYS = [
        'relation_client' => true,
        'contacts' => true,
        'companies' => true,
        'requests' => true,
        'conversations' => true,
        'tasks' => true,
        'appointments' => true,
        'quotes' => true,
        'documents' => true,
        'acquisition' => true,
        'campaigns' => true,
    ];

    public function label(string $key, string $fallback): string
    {
        if (! isset(self::KEYS[$key])) {
            return $fallback;
        }

        $value = data_get(app(OrganizationContext::class)->current()?->settings ?? [], "presentation.labels.{$key}");

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    public function isVisible(string $key): bool
    {
        if (! isset(self::KEYS[$key])) {
            return true;
        }

        return data_get(app(OrganizationContext::class)->current()?->settings ?? [], "presentation.visible.{$key}", true) !== false;
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
