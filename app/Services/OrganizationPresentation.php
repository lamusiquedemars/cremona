<?php

namespace App\Services;

use App\Tenancy\OrganizationContext;

class OrganizationPresentation
{
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
    ];

    public function label(string $key, string $fallback): string
    {
        if (! isset(self::KEYS[$key])) {
            return $fallback;
        }

        $value = app(OrganizationContext::class)->current()?->settings['presentation']['labels'][$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }
}
