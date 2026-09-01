<?php

namespace App\Filament\Platform\Pages;

use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsAgencyAuthorization;
use App\Services\GoogleAdsCredentials;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class GoogleAdsInfrastructure extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Plateforme';

    protected static ?string $navigationLabel = 'Infrastructure Google Ads';

    protected static ?string $title = 'Infrastructure Maracuja Google Ads';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.platform.pages.google-ads-infrastructure';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_platform_admin ?? false;
    }

    /** @return array{central: bool, oauth: bool, api: bool, authorization: bool, integrations: int, legacy: int} */
    public function summary(): array
    {
        $integrations = OrganizationIntegration::withoutGlobalScopes()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->get();

        $credentials = app(GoogleAdsCredentials::class);

        return [
            'central' => $credentials->centralInfrastructureIsConfigured(),
            'oauth' => $credentials->centralOAuthIsConfigured(),
            'api' => $credentials->centralApiAccessIsApproved(),
            'authorization' => app(GoogleAdsAgencyAuthorization::class)->isAuthorized(),
            'integrations' => $integrations->count(),
            'legacy' => $integrations->filter(function (OrganizationIntegration $integration): bool {
                $credentials = $integration->credentials;

                return filled($credentials['developer_token'] ?? null)
                    || filled($credentials['oauth_client_id'] ?? null)
                    || filled($credentials['oauth_client_secret'] ?? null)
                    || filled($credentials['login_customer_id'] ?? null);
            })->count(),
        ];
    }
}
