<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class GoogleAdsAgencyAuthorization
{
    private const AUTHORIZATION_KEY = 'google_ads_agency_authorization';

    private const MODE_KEY = 'google_ads_centralization_mode';

    public function refreshToken(): ?string
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        $authorization = PlatformSetting::query()->where('key', self::AUTHORIZATION_KEY)->first();
        $token = $authorization?->value['refresh_token'] ?? null;

        return is_string($token) && filled($token) ? $token : null;
    }

    public function isAuthorized(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function store(string $refreshToken): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::AUTHORIZATION_KEY],
            ['value' => ['refresh_token' => $refreshToken]],
        );
    }

    public function usesCentralInfrastructure(): bool
    {
        if (! Schema::hasTable('platform_settings')) {
            return false;
        }

        $mode = PlatformSetting::query()->where('key', self::MODE_KEY)->first();

        return ($mode?->value['enabled'] ?? false) === true;
    }

    public function enableCentralInfrastructure(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::MODE_KEY],
            ['value' => ['enabled' => true]],
        );
    }

    public function disableCentralInfrastructure(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::MODE_KEY],
            ['value' => ['enabled' => false]],
        );
    }
}
