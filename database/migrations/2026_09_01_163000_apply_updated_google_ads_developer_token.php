<?php

use App\Models\OrganizationIntegration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $developerToken = config('services.google_ads.developer_token');

        if (blank($developerToken)) {
            throw new \LogicException('Le developer token Google Ads doit être présent avant sa copie dans les organisations.');
        }

        OrganizationIntegration::withoutGlobalScopes()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->each(function (OrganizationIntegration $integration) use ($developerToken): void {
                $integration->update([
                    'credentials' => [
                        ...$integration->credentials,
                        'developer_token' => $developerToken,
                    ],
                ]);
            });
    }

    public function down(): void
    {
        // The replaced encrypted value cannot be reconstructed safely.
    }
};
