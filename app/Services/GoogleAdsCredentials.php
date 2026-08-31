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

        return $organizationCredentials;
    }

    public function centralInfrastructureIsConfigured(): bool
    {
        $central = config('services.google_ads', []);

        return filled($central['developer_token'] ?? null)
            && filled($central['oauth_client_id'] ?? null)
            && filled($central['oauth_client_secret'] ?? null);
    }

    /** @param array<string, mixed> $organizationCredentials */
    public function isReady(array $organizationCredentials): bool
    {
        $credentials = $this->resolve($organizationCredentials);

        return filled($credentials['customer_id'] ?? null)
            && filled($credentials['developer_token'] ?? null)
            && filled($credentials['oauth_client_id'] ?? null)
            && filled($credentials['oauth_client_secret'] ?? null)
            && filled($credentials['refresh_token'] ?? null);
    }
}
