<?php

namespace App\Services;

use App\Models\PlatformSetting;

class GoogleAdsAgencyAuthorization
{
    private const KEY = 'google_ads_agency_authorization';

    public function refreshToken(): ?string
    {
        $setting = PlatformSetting::query()->where('key', self::KEY)->first();
        $token = $setting?->value['refresh_token'] ?? null;

        return is_string($token) && filled($token) ? $token : null;
    }

    public function isAuthorized(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function store(string $refreshToken): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => ['refresh_token' => $refreshToken]],
        );
    }
}
