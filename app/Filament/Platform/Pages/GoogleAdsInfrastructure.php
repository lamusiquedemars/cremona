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

    /** @return array{mode: bool, oauth: bool, api: bool, authorization: bool, ready: bool, integrations: int, legacy: int} */
    public function summary(): array
    {
        $integrations = OrganizationIntegration::withoutGlobalScopes()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->get();
        $credentials = app(GoogleAdsCredentials::class);

        return [
            'mode' => $credentials->usesCentralInfrastructure(),
            'oauth' => $credentials->centralOAuthIsConfigured(),
            'api' => $credentials->centralApiAccessIsApproved(),
            'authorization' => app(GoogleAdsAgencyAuthorization::class)->isAuthorized(),
            'ready' => $credentials->centralInfrastructureIsReady(),
            'integrations' => $integrations->count(),
            'legacy' => $integrations->filter(fn (OrganizationIntegration $integration): bool => filled($integration->credentials['refresh_token'] ?? null))->count(),
        ];
    }
}
