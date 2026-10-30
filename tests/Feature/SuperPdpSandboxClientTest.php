<?php

namespace Tests\Feature;

use App\Services\SuperPdpClient;
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

    public function test_it_submits_a_cii_document_with_an_idempotency_identifier_and_reads_events(): void
    {
        config()->set('services.super_pdp.api_url', 'https://api.superpdp.test');
        Http::fake([
            'https://api.superpdp.test/oauth2/token' => Http::response(['access_token' => 'server-token'], 200),
            'https://api.superpdp.test/v1.beta/invoices*' => Http::response(['id' => 123], 200),
            'https://api.superpdp.test/v1.beta/invoice_events*' => Http::response(['data' => [['status_code' => 'api:sent']]], 200),
        ]);

        $client = app(SuperPdpClient::class);
        $submitted = $client->submitCii('client-id', 'client-secret', '<Invoice />', 'crm-01ABC', 'B2C');
        $events = $client->invoiceEvents('client-id', 'client-secret', '123');

        $this->assertSame(123, $submitted['id']);
        $this->assertSame('api:sent', $events['data'][0]['status_code']);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.superpdp.test/v1.beta/invoices?')
            && str_contains($request->url(), 'external_id=crm-01ABC')
            && str_contains($request->url(), 'processing_rule=B2C')
            && $request->hasHeader('Authorization', 'Bearer server-token')
            && $request->body() === '<Invoice />');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.superpdp.test/v1.beta/invoice_events?')
            && str_contains($request->url(), 'invoice_id=123'));
    }
}
