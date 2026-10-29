<?php

namespace App\Services;

use LogicException;

class SuperPdpSandboxClient
{
    /** @return array{company_id: ?string, verification_status: string, checked_at: string} */
    public function checkConnection(): array
    {
        $clientId = config('services.super_pdp.sandbox_client_id');
        $clientSecret = config('services.super_pdp.sandbox_client_secret');
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new LogicException('Les identifiants serveur Super PDP sandbox ne sont pas configurés.');
        }

        return app(SuperPdpClient::class)->checkConnection($clientId, $clientSecret);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.super_pdp.sandbox_client_id'))
            && filled(config('services.super_pdp.sandbox_client_secret'));
    }
}
