<?php

use App\Models\OrganizationIntegration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $shared = config('services.google_ads');

        foreach (['oauth_client_id', 'oauth_client_secret', 'login_customer_id'] as $key) {
            if (blank($shared[$key] ?? null)) {
                throw new \LogicException("La valeur Google Ads {$key} doit être présente avant le retour aux credentials par organisation.");
            }
        }

        OrganizationIntegration::withoutGlobalScopes()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->each(function (OrganizationIntegration $integration) use ($shared): void {
                $integration->update([
                    'credentials' => [
                        ...$integration->credentials,
                        'oauth_client_id' => $shared['oauth_client_id'],
                        'oauth_client_secret' => $shared['oauth_client_secret'],
                        'login_customer_id' => preg_replace('/\D/', '', (string) $shared['login_customer_id']),
                    ],
                ]);
            });
    }

    public function down(): void
    {
        // The prior encrypted values cannot be reconstructed safely.
    }
};
