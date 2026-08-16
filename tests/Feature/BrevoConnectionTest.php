<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Filament\Resources\BrevoConnections\BrevoConnectionResource;
use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Services\BrevoClient;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_brevo_api_key_is_verified_and_sent_only_in_the_api_header(): void
    {
        Http::fake([
            'api.brevo.com/v3/account' => Http::response([
                'email' => 'account@example.test',
                'companyName' => 'Atelier Test',
            ]),
        ]);

        $account = app(BrevoClient::class)->account('xkeysib-private-key');

        $this->assertSame('account@example.test', $account['email']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.brevo.com/v3/account'
            && $request->hasHeader('api-key', 'xkeysib-private-key'));
    }

    public function test_brevo_configuration_reuses_the_encrypted_integration_vault(): void
    {
        $organization = Organization::factory()->create();
        $administrator = User::factory()->create();
        $administrator->organizations()->attach($organization, [
            'role' => OrganizationRole::Administrator->value,
        ]);

        $integration = app(OrganizationContext::class)->run($organization, function () use ($administrator): OrganizationIntegration {
            $manager = app(OrganizationIntegrationManager::class);
            $manager->configure('brevo', 'meetings', [
                'api_key' => 'xkeysib-secret-value',
                'booking_url' => 'https://meet.brevo.com/atelier-test',
                'timezone' => 'Europe/Paris',
                'mode' => 'after_review',
            ], $administrator);

            return $manager->configure('brevo', 'meetings', [
                'api_key' => 'xkeysib-secret-value',
                'booking_url' => 'https://meet.brevo.com/atelier-test',
                'timezone' => 'Europe/Paris',
                'mode' => 'direct',
            ], $administrator);
        });

        $stored = (string) DB::table('organization_integrations')
            ->where('id', $integration->id)
            ->value('credentials');

        $this->assertStringNotContainsString('xkeysib-secret-value', $stored);
        $this->assertSame('direct', $integration->credentials['mode']);
        $this->assertSame(1, OrganizationIntegration::withoutGlobalScopes()->count());
        $this->assertSame(2, OrganizationAuditLog::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('event', [
                'integration.created',
                'integration.updated',
            ])->count());
    }

    public function test_only_integration_managers_can_open_brevo_configuration(): void
    {
        $organization = Organization::factory()->create();
        $administrator = User::factory()->create();
        $collaborator = User::factory()->create();
        $administrator->organizations()->attach($organization, [
            'role' => OrganizationRole::Administrator->value,
        ]);
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        app(OrganizationContext::class)->run($organization, fn () => app(OrganizationIntegrationManager::class)
            ->configure('brevo', 'meetings', [
                'api_key' => 'encrypted-secret',
                'booking_url' => 'https://meet.brevo.com/atelier-test',
                'timezone' => 'Europe/Paris',
                'mode' => 'after_review',
            ], $administrator));

        $url = BrevoConnectionResource::getUrl('index', tenant: $organization);

        $this->actingAs($administrator)
            ->get($url)
            ->assertOk()
            ->assertSee('Brevo Meetings')
            ->assertSee('https://meet.brevo.com/atelier-test')
            ->assertDontSee('encrypted-secret');

        $this->actingAs($collaborator)
            ->get($url)
            ->assertForbidden();
    }
}
