<?php

namespace Tests\Feature;

use App\Services\SuperPdpSandboxClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuperPdpSandboxClientTest extends TestCase
{
    public function test_it_checks_the_server_side_sandbox_connection_without_an_oauth_redirect(): void
    {
        config()->set('services.super_pdp.sandbox_client_id', 'client-id');
        config()->set('services.super_pdp.sandbox_client_secret', 'client-secret');
        config()->set('services.super_pdp.api_url', 'https://api.superpdp.test');
        Http::fake([
            'https://api.superpdp.test/oauth2/token' => Http::response(['access_token' => 'server-token'], 200),
            'https://api.superpdp.test/v1.beta/oauth2_sessions/me' => Http::response([
                'company_id' => 'sandbox-company',
                'company_verification_status' => 'pending',
            ], 200),
        ]);

        $state = app(SuperPdpSandboxClient::class)->checkConnection();

        $this->assertSame('sandbox-company', $state['company_id']);
        $this->assertSame('pending', $state['verification_status']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.superpdp.test/oauth2/token'
            && $request['grant_type'] === 'client_credentials');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.superpdp.test/v1.beta/oauth2_sessions/me'
            && $request->hasHeader('Authorization', 'Bearer server-token'));
    }
}
