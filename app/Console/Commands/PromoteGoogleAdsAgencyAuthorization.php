<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsAgencyAuthorization;
use Illuminate\Console\Command;

class PromoteGoogleAdsAgencyAuthorization extends Command
{
    protected $signature = 'cremona:promote-google-ads-authorization {organization : Slug de l’organisation qui possède l’autorisation valide}';

    protected $description = 'Promote an existing organization Google Ads authorization to the central Maracuja vault.';

    public function handle(GoogleAdsAgencyAuthorization $authorization): int
    {
        $organization = Organization::query()->where('slug', $this->argument('organization'))->firstOrFail();
        $integration = OrganizationIntegration::withoutGlobalScopes()
            ->where('organization_id', $organization->getKey())
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->firstOrFail();
        $refreshToken = $integration->credentials['refresh_token'] ?? null;

        if (! is_string($refreshToken) || blank($refreshToken)) {
            $this->error('Cette organisation ne possède pas d’autorisation Google Ads exploitable.');

            return self::FAILURE;
        }

        $authorization->store($refreshToken);
        $this->info('Autorisation Google Ads agence centralisée. Aucun token n’a été affiché.');

        return self::SUCCESS;
    }
}
