<?php

namespace App\Services;

class GoogleAdsCredentials
{
    /**
     * The organization always owns its Google Ads customer ID. Shared agency
     * credentials are used only after an explicit platform-level activation.
     * No credential is copied into an organization during that activation.
     *
     * @param  array<string, mixed>  $organizationCredentials
     * @return array<string, mixed>
     */
    public function resolve(array $organizationCredentials): array
    {
        if (! $this->usesCentralInfrastructure()) {
            return $organizationCredentials;
        }

        $central = config('services.google_ads', []);

        return [
            ...$organizationCredentials,
            'developer_token' => $central['developer_token'] ?? null,
            'oauth_client_id' => $central['oauth_client_id'] ?? null,
            'oauth_client_secret' => $central['oauth_client_secret'] ?? null,
            'login_customer_id' => $central['login_customer_id'] ?? null,
            'refresh_token' => app(GoogleAdsAgencyAuthorization::class)->refreshToken(),
        ];
    }

    public function usesCentralInfrastructure(): bool
    {
        return app(GoogleAdsAgencyAuthorization::class)->usesCentralInfrastructure();
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

    public function centralInfrastructureIsReady(): bool
    {
        return $this->centralOAuthIsConfigured()
            && $this->centralApiAccessIsApproved()
            && app(GoogleAdsAgencyAuthorization::class)->isAuthorized();
    }

    /** @param array<string, mixed> $credentials */
    public function isReady(array $credentials): bool
    {
        $credentials = $this->resolve($credentials);

        return filled($credentials['customer_id'] ?? null)
            && filled($credentials['developer_token'] ?? null)
            && filled($credentials['oauth_client_id'] ?? null)
            && filled($credentials['oauth_client_secret'] ?? null)
            && filled($credentials['refresh_token'] ?? null);
    }

    /** @param array<string, mixed> $credentials */
    public function connectionState(array $credentials): string
    {
        if ($this->usesCentralInfrastructure()) {
            return $this->isReady($credentials)
                ? 'Prêt à synchroniser — infrastructure Maracuja'
                : 'Infrastructure Maracuja à vérifier';
        }

        return $this->isReady($credentials)
            ? 'Prêt à synchroniser — configuration historique'
            : 'Configuration à compléter';
    }
}
