<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Tenancy\OrganizationContext;

final class OrganizationModuleAccess
{
    public function enabled(string $module, ?Organization $organization = null): bool
    {
        $organization ??= app(OrganizationContext::class)->current();

        return $organization instanceof Organization
            && OrganizationModule::withoutGlobalScopes()
                ->where('organization_id', $organization->getKey())
                ->where('module', $module)
                ->where('enabled', true)
                ->exists();
    }
}
