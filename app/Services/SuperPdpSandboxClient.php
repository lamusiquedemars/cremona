<?php

namespace App\Services;

use Illuminate\Support\Str;
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

    public function generateTestInvoice(bool $b2c = false): string
    {
        $clientId = config('services.super_pdp.sandbox_client_id');
        $clientSecret = config('services.super_pdp.sandbox_client_secret');
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new LogicException('Les identifiants serveur Super PDP sandbox ne sont pas configurés.');
        }

        return app(SuperPdpClient::class)->generateSandboxInvoice($clientId, $clientSecret, $b2c);
    }

    /** @return array<string, mixed> */
    public function validateAndSubmitTestInvoice(bool $b2c = false): array
    {
        $clientId = config('services.super_pdp.sandbox_client_id');
        $clientSecret = config('services.super_pdp.sandbox_client_secret');
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new LogicException('Les identifiants serveur Super PDP sandbox ne sont pas configurés.');
        }
        $content = app(SuperPdpClient::class)->generateSandboxInvoice($clientId, $clientSecret, $b2c);
        $report = app(SuperPdpClient::class)->validateCii($clientId, $clientSecret, $content, 'sandbox-test.xml');
        if (data_get($report, 'data.0.is_valid') !== true) {
            throw new LogicException('Le CII sandbox généré n’a pas été validé par Super PDP.');
        }

        return app(SuperPdpClient::class)->submitCii($clientId, $clientSecret, $content, 'sandbox-'.Str::uuid(), $b2c ? 'B2C' : 'B2B');
    }

    public function setMonthlyVatRegime(): void
    {
        $clientId = config('services.super_pdp.sandbox_client_id');
        $clientSecret = config('services.super_pdp.sandbox_client_secret');
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new LogicException('Les identifiants serveur Super PDP sandbox ne sont pas configurés.');
        }

        app(SuperPdpClient::class)->updateVatRegime($clientId, $clientSecret, 'monthly');
    }
}
