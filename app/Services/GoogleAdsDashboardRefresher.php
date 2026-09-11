<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use Throwable;

final class GoogleAdsDashboardRefresher
{
    public function __construct(
        private readonly GoogleAdsCredentials $credentials,
        private readonly GoogleAdsReportingClient $reporting,
    ) {}

    /**
     * Actualise les résultats lorsque le dashboard affiche des données âgées
     * d'au moins quinze minutes. La synchronisation est strictement en lecture
     * seule côté Google Ads et ne doit jamais empêcher l'ouverture du dashboard.
     */
    public function refreshIfStale(Organization $organization): void
    {
        $integration = OrganizationIntegration::query()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->where('status', 'active')
            ->first();

        if ($integration === null || ! $this->credentials->isReady($integration->credentials)) {
            return;
        }

        $isStale = Campaign::query()
            ->where('channel', 'google_ads')
            ->where('status', CampaignStatus::Active)
            ->whereNotNull('external_reference')
            ->where(fn ($query) => $query
                ->whereNull('google_ads_synced_at')
                ->orWhere('google_ads_synced_at', '<', now()->subMinutes(15)))
            ->exists();

        if (! $isStale) {
            return;
        }

        try {
            $this->reporting->sync($integration);
        } catch (Throwable) {
            // Le service conserve le diagnostic sur l'intégration. Le dashboard reste consultable.
        }
    }
}
