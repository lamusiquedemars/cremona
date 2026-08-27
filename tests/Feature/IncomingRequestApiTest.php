<?php

namespace Tests\Feature;

use App\Models\IncomingRequest;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Person;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncomingRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_channel_can_submit_an_idempotent_request(): void
    {
        $organization = Organization::factory()->create();
        $token = $this->createToken($organization);
        $payload = $this->payload();

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'site-42:submission-123')
            ->postJson('/api/v1/incoming-requests', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'new');

        $second = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'site-42:submission-123')
            ->postJson('/api/v1/incoming-requests', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $this->within($organization, fn (): int => IncomingRequest::query()->count()));
        $this->assertSame(0, $this->within($organization, fn (): int => Person::query()->count()));

        $request = $this->within(
            $organization,
            fn (): IncomingRequest => IncomingRequest::query()->with(['answers', 'consents'])->sole(),
        );
        $this->assertSame('Camille Martin', $request->name_snapshot);
        $this->assertSame('site-42', $request->source_site_reference);
        $this->assertSame('google', $request->attribution_source);
        $this->assertSame('search', $request->attribution_medium);
        $this->assertSame('criminal-cuiaba', $request->attribution_campaign);
        $this->assertSame('first-click', $request->attribution_first_touch['gclid']);
        $this->assertSame('/contact?utm_source=google', $request->attribution_last_touch['landing_page']);
        $this->assertSame('first_party', $request->attribution_method);
        $this->assertSame('1.00', $request->attribution_confidence);
        $this->assertCount(1, $request->answers);
        $this->assertCount(1, $request->consents);
    }

    public function test_invalid_or_revoked_credentials_are_rejected(): void
    {
        $organization = Organization::factory()->create();
        $issued = $this->issueToken($organization);

        $this->withToken('invalid.token')
            ->withHeader('Idempotency-Key', 'invalid-1')
            ->postJson('/api/v1/incoming-requests', $this->payload())
            ->assertUnauthorized();

        app(OrganizationContext::class)->run(
            $organization,
            fn () => app(OrganizationIntegrationManager::class)->revoke($issued['integration']),
        );

        $this->withToken($issued['token'])
            ->withHeader('Idempotency-Key', 'revoked-1')
            ->postJson('/api/v1/incoming-requests', $this->payload())
            ->assertUnauthorized();
    }

    public function test_the_contract_validates_required_fields_and_idempotency_conflicts(): void
    {
        $organization = Organization::factory()->create();
        $token = $this->createToken($organization);

        $this->withToken($token)
            ->postJson('/api/v1/incoming-requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key', 'source', 'request']);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'conflict-1')
            ->postJson('/api/v1/incoming-requests', $this->payload())
            ->assertCreated();

        $changed = $this->payload();
        $changed['request']['message'] = 'A different payload.';

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'conflict-1')
            ->postJson('/api/v1/incoming-requests', $changed)
            ->assertConflict();
    }

    public function test_api_tokens_are_only_stored_as_hashes(): void
    {
        $organization = Organization::factory()->create();
        $issued = $this->issueToken($organization);
        [, $secret] = explode('.', $issued['token'], 2);

        $stored = DB::table('organization_integrations')
            ->where('id', $issued['integration']->id)
            ->first();

        $this->assertNotNull($stored->token_hash);
        $this->assertStringNotContainsString($secret, $stored->token_hash);
        $this->assertStringNotContainsString($secret, $stored->credentials);
        $this->assertArrayNotHasKey('credentials', $issued['integration']->toArray());
        $this->assertArrayNotHasKey('token_hash', $issued['integration']->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'source' => [
                'channel' => 'website',
                'name' => 'maracuja-cms',
                'site_reference' => 'site-42',
                'form_reference' => 'contact-v1',
            ],
            'contact' => [
                'name' => 'Camille Martin',
                'email' => 'camille@example.test',
            ],
            'attribution' => [
                'method' => 'first_party',
                'confidence' => 1,
                'first_touch' => [
                    'utm_source' => 'google',
                    'utm_medium' => 'search',
                    'utm_campaign' => 'criminal-cuiaba',
                    'gclid' => 'first-click',
                    'landing_page' => '/criminal',
                    'captured_at' => '2026-08-22T10:00:00-04:00',
                ],
                'last_touch' => [
                    'utm_source' => 'google',
                    'utm_medium' => 'search',
                    'utm_campaign' => 'criminal-cuiaba',
                    'utm_term' => 'advogado criminalista cuiaba',
                    'gclid' => 'last-click',
                    'landing_page' => '/contact?utm_source=google',
                    'referrer' => 'https://www.google.com/',
                    'captured_at' => '2026-08-22T10:15:00-04:00',
                ],
            ],
            'request' => [
                'subject' => 'Projet sur mesure',
                'message' => 'Je souhaite être recontactée.',
                'urgency' => 'normal',
            ],
            'answers' => [[
                'field_key' => 'preferred_channel',
                'label' => 'Canal préféré',
                'value' => 'email',
            ]],
            'consent' => [
                'purpose' => 'respond_to_request',
                'channel' => 'email',
                'status' => 'granted',
                'statement' => 'J’autorise une réponse à cette demande.',
                'statement_version' => 'v1',
            ],
        ];
    }

    private function createToken(Organization $organization): string
    {
        return $this->issueToken($organization)['token'];
    }

    /**
     * @return array{integration: OrganizationIntegration, token: string}
     */
    private function issueToken(Organization $organization): array
    {
        return app(OrganizationContext::class)->run(
            $organization,
            fn (): array => app(OrganizationIntegrationManager::class)
                ->createApiToken('maracuja_cms', fake()->unique()->slug(2), null),
        );
    }

    private function within(Organization $organization, callable $callback): mixed
    {
        return app(OrganizationContext::class)->run($organization, $callback);
    }
}
