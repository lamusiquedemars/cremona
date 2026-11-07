<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationSite;

final class ConnectedSiteAdministration
{
    public function urlFor(?Organization $organization): ?string
    {
        if (! $organization instanceof Organization) {
            return null;
        }

        $site = $organization->sites()
            ->where('site_portal_enabled', true)
            ->where('status', 'active')
            ->whereNotNull('base_url')
            ->orderBy('id')
            ->first();

        if ($site instanceof OrganizationSite && filled($site->base_url)) {
            return $site->base_url;
        }

        // Compatibility with the deployed Demo Maracuja pilot until its
        // connection has been registered in the platform console.
        foreach (config('cremona.site_portal.clients', []) as $client) {
            if (is_array($client)
                && ($client['organization'] ?? null) === $organization->slug
                && is_string($client['site_url'] ?? null)
                && filled($client['site_url'])) {
                return $client['site_url'];
            }
        }

        return null;
    }
}
