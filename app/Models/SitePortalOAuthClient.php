<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

final class SitePortalOAuthClient extends Client
{
    /**
     * Trusted sites may open their portal without a second confirmation screen.
     * The exception is deliberately narrow: a configured site client, the sole
     * portal scope, and a user who can actually access its organization.
     *
     * @param  array<int, object>  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $portal = config("cremona.site_portal.clients.{$this->getKey()}");
        if (! is_array($portal)) {
            return false;
        }

        $scopeIds = collect($scopes)
            ->map(fn (object $scope): mixed => $scope->id ?? null)
            ->filter()
            ->sort()
            ->values()
            ->all();

        if ($scopeIds !== ['site:access']) {
            return false;
        }

        $organization = Organization::query()
            ->where('slug', $portal['organization'] ?? null)
            ->first();

        return $organization !== null && $user->canAccessTenant($organization);
    }
}
