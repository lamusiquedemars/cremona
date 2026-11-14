<?php

namespace Tests\Feature;

use App\Services\GoogleAdsAgencyAuthorization;
use App\Services\GoogleAdsCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GoogleAdsCentralizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_centralization_is_off_by_default_and_never_copies_organization_credentials(): void
    {
        Config::set('services.google_ads', [
            'developer_token' => 'central-developer-token',
            'oauth_client_id' => 'central-client-id',
            'oauth_client_secret' => 'central-client-secret',
            'login_customer_id' => '5950759380',
            'api_access_level' => 'basic',
        ]);

        $legacy = [
            'customer_id' => '2005073692',
            'developer_token' => 'legacy-developer-token',
            'oauth_client_id' => 'legacy-client-id',
            'oauth_client_secret' => 'legacy-client-secret',
            'refresh_token' => 'legacy-refresh-token',
        ];

        $authorization = app(GoogleAdsAgencyAuthorization::class);
        $credentials = app(GoogleAdsCredentials::class);

        $this->assertFalse($credentials->usesCentralInfrastructure());
        $this->assertSame($legacy, $credentials->resolve($legacy));

        $authorization->store('central-refresh-token');
        $authorization->enableCentralInfrastructure();
        $resolved = $credentials->resolve($legacy);

        $this->assertTrue($credentials->usesCentralInfrastructure());
        $this->assertSame('2005073692', $resolved['customer_id']);
        $this->assertSame('central-developer-token', $resolved['developer_token']);
        $this->assertSame('central-client-id', $resolved['oauth_client_id']);
        $this->assertSame('central-client-secret', $resolved['oauth_client_secret']);
        $this->assertSame('central-refresh-token', $resolved['refresh_token']);
        $this->assertSame('legacy-developer-token', $legacy['developer_token']);
        $this->assertSame('legacy-refresh-token', $legacy['refresh_token']);

        $authorization->disableCentralInfrastructure();
        $this->assertSame($legacy, $credentials->resolve($legacy));
    }
}
