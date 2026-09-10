<?php

namespace App\Services;

use App\Models\Organization;
use App\Tenancy\OrganizationContext;

final class OrganizationModuleAccess
{
    public function enabled(string $module, ?Organization $organization = null): bool
    {
        $organization ??= app(OrganizationContext::class)->current();

        if (! $organization instanceof Organization) {
            return false;
        }

        return $organization->modules()
            ->where('module', $module)
            ->where('enabled', true)
            ->exists();
    }

    /** @param array<int, string> $modules */
    public function anyEnabled(array $modules, ?Organization $organization = null): bool
    {
        $organization ??= app(OrganizationContext::class)->current();

        if (! $organization instanceof Organization || $modules === []) {
            return false;
        }

        return $organization->modules()
            ->whereIn('module', $modules)
            ->where('enabled', true)
            ->exists();
    }
}
