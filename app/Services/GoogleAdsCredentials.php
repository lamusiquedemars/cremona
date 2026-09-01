<?php

namespace App\Services;

class GoogleAdsCredentials
{
    /**
     * Keep customer-owned data on the organization while allowing Maracuja's
     * shared API application credentials to live in server configuration.
     *
     * @param  array<string, mixed>  $organizationCredentials
     * @return array<string, mixed>
     */
    public function resolve(array $organizationCredentials): array
    {
        $central = config('services.google_ads', []);

        foreach (['developer_token', 'oauth_client_id', 'oauth_client_secret', 'login_customer_id'] as $key) {
            if (filled($central[$key] ?? null)) {
                $organizationCredentials[$key] = $central[$key];
            }
        }

        $agencyRefreshToken = app(GoogleAdsAgencyAuthorization::class)->refreshToken();
        if ($agencyRefreshToken !== null) {
            $organizationCredentials['refresh_token'] = $agencyRefreshToken;
        }

        return $organizationCredentials;
    }

    public function centralInfrastructureIsConfigured(): bool
    {
        return $this->centralOAuthIsConfigured()
            && $this->centralApiAccessIsApproved()
            && app(GoogleAdsAgencyAuthorization::class)->isAuthorized();
    }

    public function centralOAuthIsConfigured(): bool
    {
        $central = config('services.google_ads', []);

        return filled($central['oauth_client_id'] ?? null)
            && filled($central['oauth_client_secret'] ?? null);
    }

    public function centralApiAccessIsApproved(): bool
    {
        $central = config('services.google_ads', []);

        return filled($central['developer_token'] ?? null)
            && in_array(strtolower((string) ($central['api_access_level'] ?? 'pending')), ['basic', 'standard'], true);
    }

    /** @param array<string, mixed> $organizationCredentials */
    public function connectionState(array $organizationCredentials): string
    {
        if (! $this->centralOAuthIsConfigured()) {
            return 'Infrastructure Maracuja à configurer';
        }

        if (! filled($organizationCredentials['refresh_token'] ?? null)) {
            return 'Autorisation Google requise';
        }

        if (! $this->centralApiAccessIsApproved()) {
            return 'Accès API Google en attente';
        }

        return $this->isReady($organizationCredentials) ? 'Connecté' : 'Configuration à vérifier';
    }

    /** @param array<string, mixed> $organizationCredentials */
    public function isReady(array $organizationCredentials): bool
    {
        $credentials = $this->resolve($organizationCredentials);
        $central = config('services.google_ads', []);
        $usesCentralInfrastructure = collect(['developer_token', 'oauth_client_id', 'oauth_client_secret'])
            ->contains(fn (string $key): bool => filled($central[$key] ?? null));

        $infrastructureReady = $usesCentralInfrastructure
            ? $this->centralInfrastructureIsConfigured()
            : filled($credentials['developer_token'] ?? null)
                && filled($credentials['oauth_client_id'] ?? null)
                && filled($credentials['oauth_client_secret'] ?? null);

        return filled($credentials['customer_id'] ?? null)
            && $infrastructureReady
            && filled($credentials['refresh_token'] ?? null);
    }
}
