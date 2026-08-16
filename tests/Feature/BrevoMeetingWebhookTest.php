<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrevoMeetingWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_brevo_meeting_events_create_and_update_one_safe_crm_projection(): void
    {
        $organization = Organization::factory()->create();
        $host = User::factory()->create(['email' => 'host@example.test']);
        $host->organizations()->attach($organization);

        [$token, $person] = app(OrganizationContext::class)->run(
            $organization,
            function () use ($host): array {
                $person = Person::query()->create(['display_name' => 'Camille Participant']);
                $person->contactMethods()->create([
                    'type' => 'email',
                    'value' => 'camille@example.test',
                ]);
                $manager = app(OrganizationIntegrationManager::class);
                $integration = $manager->configure('brevo', 'meetings', [
                    'api_key' => 'encrypted-api-key',
                    'booking_url' => 'https://meet.brevo.com/atelier',
                    'timezone' => 'Europe/Paris',
                    'mode' => 'after_review',
                ], $host);

                return [$manager->rotateApiToken($integration, $host), $person];
            },
        );
        $payload = $this->payload();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson(route('api.v1.integrations.brevo.meetings.store', ['event' => 'booked']), $payload, $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');
        $this->postJson(route('api.v1.integrations.brevo.meetings.store', ['event' => 'booked']), $payload, $headers)
            ->assertOk();

        $appointment = Appointment::withoutGlobalScopes()->sole();
        $this->assertSame($organization->id, $appointment->organization_id);
        $this->assertSame($person->id, $appointment->person_id);
        $this->assertSame($host->id, $appointment->assigned_user_id);
        $this->assertSame('brevo', $appointment->provider);
        $this->assertSame('Europe/Paris', $appointment->timezone);
        $this->assertSame('12 rue des Luthiers', $appointment->location);
        $this->assertNull($appointment->description);
        $this->assertSame(1, Appointment::withoutGlobalScopes()->count());

        $this->postJson(route('api.v1.integrations.brevo.meetings.store', ['event' => 'cancelled']), $payload, $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertNotNull($appointment->cancelled_at);
        $this->assertSame(1, Appointment::withoutGlobalScopes()->count());

        $this->postJson(route('api.v1.integrations.brevo.meetings.store', ['event' => 'started']), $payload, $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_brevo_webhooks_reject_wrong_provider_tokens_and_invalid_payloads(): void
    {
        $organization = Organization::factory()->create();

        [$brevoToken, $maracujaToken] = app(OrganizationContext::class)->run(
            $organization,
            function (): array {
                $manager = app(OrganizationIntegrationManager::class);
                $brevo = $manager->configure('brevo', 'meetings', [
                    'api_key' => 'encrypted-api-key',
                    'booking_url' => 'https://meet.brevo.com/atelier',
                    'timezone' => 'Europe/Paris',
                    'mode' => 'after_review',
                ]);

                return [
                    $manager->rotateApiToken($brevo),
                    $manager->createApiToken('maracuja_cms', 'site')['token'],
                ];
            },
        );
        $url = route('api.v1.integrations.brevo.meetings.store', ['event' => 'booked']);

        $this->postJson($url, $this->payload(), [
            'Authorization' => "Bearer {$maracujaToken}",
        ])->assertUnauthorized();
        $this->postJson($url, ['meeting_name' => 'Incomplete'], [
            'Authorization' => "Bearer {$brevoToken}",
        ])->assertUnprocessable();
        $this->assertSame(0, Appointment::withoutGlobalScopes()->count());
    }

    public function test_webhook_tokens_are_only_stored_as_hashes(): void
    {
        $organization = Organization::factory()->create();

        [$integration, $token] = app(OrganizationContext::class)->run(
            $organization,
            function (): array {
                $manager = app(OrganizationIntegrationManager::class);
                $integration = $manager->configure('brevo', 'meetings', [
                    'api_key' => 'encrypted-api-key',
                    'booking_url' => 'https://meet.brevo.com/atelier',
                ]);

                return [$integration, $manager->rotateApiToken($integration)];
            },
        );
        $stored = DB::table('organization_integrations')->where('id', $integration->id)->first();

        $this->assertNotNull($stored->token_hash);
        $this->assertStringNotContainsString($token, (string) $stored->token_hash);
        $this->assertStringNotContainsString($token, (string) $stored->credentials);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'account_email' => 'host@example.test',
            'event_participants' => [[
                'EMAIL' => 'camille@example.test',
                'FIRSTNAME' => 'Camille',
                'LASTNAME' => 'Participant',
            ]],
            'meeting_name' => 'Diagnostic de l’instrument',
            'meeting_start_timestamp' => '2026-09-03T12:00:00.000Z',
            'meeting_end_timestamp' => '2026-09-03T12:45:00.000Z',
            'meeting_location' => 'Atelier',
            'meeting_address' => '12 rue des Luthiers',
            'meeting_notes' => 'Donnée sensible qui ne doit pas être recopiée.',
            'questions_and_answers' => [[
                'question' => 'Détail confidentiel',
                'answer' => 'Ne pas conserver',
            ]],
        ];
    }
}
