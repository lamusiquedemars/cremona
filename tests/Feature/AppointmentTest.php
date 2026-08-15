<?php

namespace Tests\Feature;

use App\Enums\AppointmentModality;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationRole;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_appointment_is_a_tenant_scoped_crm_projection(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($organization);

        app(OrganizationContext::class)->run($organization, function () use ($member): void {
            $person = Person::query()->create(['display_name' => 'Camille Rendez-vous']);
            $appointment = Appointment::query()->create([
                'assigned_user_id' => $member->id,
                'person_id' => $person->id,
                'title' => 'Présentation du projet',
                'status' => AppointmentStatus::Scheduled,
                'starts_at' => '2026-09-03 14:00:00',
                'ends_at' => '2026-09-03 14:45:00',
                'timezone' => 'Europe/Paris',
                'modality' => AppointmentModality::Video,
                'meeting_url' => 'https://meet.brevo.com/example',
                'provider' => 'brevo',
                'external_reference' => 'meeting-42',
            ]);

            $this->assertNotNull($appointment->public_id);
            $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
            $this->assertSame(AppointmentModality::Video, $appointment->modality);
            $this->assertTrue($appointment->person->is($person));
            $this->assertSame('meeting-42', $appointment->external_reference);

            $appointment->update(['status' => AppointmentStatus::Cancelled]);
            $this->assertNotNull($appointment->refresh()->cancelled_at);
        });

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_an_appointment_rejects_invalid_times_and_cross_tenant_contacts(): void
    {
        $firstOrganization = Organization::factory()->create();
        $secondOrganization = Organization::factory()->create();
        $person = app(OrganizationContext::class)->run(
            $firstOrganization,
            fn () => Person::query()->create(['display_name' => 'Contact privé']),
        );

        app(OrganizationContext::class)->run($secondOrganization, function () use ($person): void {
            try {
                Appointment::query()->create([
                    'person_id' => $person->id,
                    'title' => 'Relation interdite',
                    'starts_at' => '2026-09-03 14:00:00',
                    'ends_at' => '2026-09-03 15:00:00',
                ]);
                $this->fail('A cross-tenant contact should be rejected.');
            } catch (LogicException) {
                $this->assertSame(0, Appointment::query()->count());
            }

            $this->expectException(LogicException::class);
            Appointment::query()->create([
                'title' => 'Horaire invalide',
                'starts_at' => '2026-09-03 15:00:00',
                'ends_at' => '2026-09-03 14:00:00',
            ]);
        });
    }

    public function test_viewers_can_consult_appointments_but_only_collaborators_can_create_them(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $collaborator = User::factory()->create();
        $viewer->organizations()->attach($organization, ['role' => OrganizationRole::Viewer->value]);
        $collaborator->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        $appointment = app(OrganizationContext::class)->run(
            $organization,
            fn () => Appointment::query()->create([
                'title' => 'Rendez-vous visible',
                'starts_at' => '2026-09-03 14:00:00',
                'ends_at' => '2026-09-03 15:00:00',
            ]),
        );

        $this->actingAs($viewer)
            ->get(AppointmentResource::getUrl('index', tenant: $organization))
            ->assertOk()
            ->assertSee('Rendez-vous visible');
        $this->actingAs($viewer)
            ->get(AppointmentResource::getUrl('view', ['record' => $appointment], tenant: $organization))
            ->assertOk()
            ->assertDontSee('Modifier le rendez-vous');
        $this->actingAs($viewer)
            ->get(AppointmentResource::getUrl('create', tenant: $organization))
            ->assertForbidden();
        $this->actingAs($collaborator)
            ->get(AppointmentResource::getUrl('create', tenant: $organization))
            ->assertOk();
    }

    public function test_an_appointment_is_visible_from_its_contact_page(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $viewer->organizations()->attach($organization, ['role' => OrganizationRole::Viewer->value]);

        [$person] = app(OrganizationContext::class)->run($organization, function (): array {
            $person = Person::query()->create(['display_name' => 'Contact agenda']);
            Appointment::query()->create([
                'person_id' => $person->id,
                'title' => 'Rendez-vous depuis la fiche',
                'starts_at' => '2026-09-03 14:00:00',
                'ends_at' => '2026-09-03 15:00:00',
            ]);

            return [$person];
        });

        $this->actingAs($viewer)
            ->get(PersonResource::getUrl('view', [
                'record' => $person,
                'relation' => 'appointments',
            ], tenant: $organization))
            ->assertOk()
            ->assertSee('Rendez-vous depuis la fiche');
    }
}
