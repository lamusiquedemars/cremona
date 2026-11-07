<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationSite;

final class SitePortalClientRegistry
{
    /** @return array{organization: Organization, secret: string, items: array<int, string>, site_name: string, site_url: ?string, logout_redirect_uri: ?string}|null */
    public function find(string $clientId): ?array
    {
        $site = OrganizationSite::query()
            ->with('organization')
            ->where('site_portal_client_id', $clientId)
            ->where('site_portal_enabled', true)
            ->where('status', 'active')
            ->first();

        if ($site instanceof OrganizationSite && $site->organization instanceof Organization && $site->organization->status === 'active' && filled($site->site_portal_secret)) {
            return [
                'organization' => $site->organization,
                'secret' => $site->site_portal_secret,
                'items' => array_values($site->site_portal_items ?? []),
                'site_name' => $site->name,
                'site_url' => $site->base_url,
                'logout_redirect_uri' => $site->site_portal_logout_redirect_uri,
            ];
        }

        // Compatibility with the already deployed Demo Maracuja pilot.
        $legacy = config('cremona.site_portal.clients.'.$clientId);
        if (! is_array($legacy) || ! is_string($legacy['logout_secret'] ?? null)) {
            return null;
        }
        $organization = Organization::query()->where('slug', $legacy['organization'] ?? null)->where('status', 'active')->first();
        if (! $organization instanceof Organization) {
            return null;
        }

        return ['organization' => $organization, 'secret' => $legacy['logout_secret'], 'items' => $legacy['items'] ?? [], 'site_name' => $legacy['site_name'] ?? $organization->name, 'site_url' => $legacy['site_url'] ?? null, 'logout_redirect_uri' => $legacy['logout_redirect_uri'] ?? null];
    }
}
