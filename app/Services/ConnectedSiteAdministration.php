<?php

namespace App\Services;

use App\Models\Organization;

final class ConnectedSiteAdministration
{
    public function urlFor(?Organization $organization): ?string
    {
        if (! $organization instanceof Organization) {
            return null;
        }

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
