<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrganizationIntegration;
use LogicException;

class SuperPdpConnectionResolver
{
    /** @return array{client_id: string, client_secret: string} */
    public function credentials(Invoice $invoice): array
    {
        if (app()->environment(['local', 'testing'])) {
            $sandbox = OrganizationIntegration::withoutGlobalScopes()
                ->where('organization_id', $invoice->organization_id)
                ->where('provider', 'super_pdp')->where('name', 'sandbox')->where('status', 'active')->first();
            $clientId = config('services.super_pdp.sandbox_client_id');
            $clientSecret = config('services.super_pdp.sandbox_client_secret');

            if ($sandbox !== null && is_string($clientId) && is_string($clientSecret) && $clientId !== '' && $clientSecret !== '') {
                return ['client_id' => $clientId, 'client_secret' => $clientSecret];
            }
        }

        $integration = OrganizationIntegration::withoutGlobalScopes()
            ->where('organization_id', $invoice->organization_id)
            ->where('provider', 'super_pdp')->where('name', 'production')->where('status', 'active')->first();
        $credentials = $integration?->credentials ?? [];
        if (! is_string($credentials['client_id'] ?? null) || ! is_string($credentials['client_secret'] ?? null)) {
            throw new LogicException('La connexion de facturation électronique n’est pas prête pour cette organisation.');
        }

        return ['client_id' => $credentials['client_id'], 'client_secret' => $credentials['client_secret']];
    }
}
