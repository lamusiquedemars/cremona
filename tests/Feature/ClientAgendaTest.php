<?php

namespace Tests\Feature;

use App\Enums\AppointmentModality;
use App\Enums\AppointmentStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\OrganizationRole;
use App\Filament\Pages\ClientAgenda;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Models\Appointment;
use App\Models\CrmTask;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationModuleRegistry;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_agenda_groups_scheduled_appointments_and_dated_tasks_in_the_active_organization_week(): void
    {
        $organization = Organization::factory()->create(['settings' => ['timezone' => 'Europe/Paris']]);
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'appointments']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        [$appointment, $task] = app(OrganizationContext::class)->run($organization, function (): array {
            $appointment = Appointment::query()->create([
                'title' => 'Dépôt du violon de Camille',
                'status' => AppointmentStatus::Scheduled,
                'starts_at' => now()->setTime(10, 0),
                'ends_at' => now()->setTime(10, 30),
                'timezone' => 'Europe/Paris',
                'modality' => AppointmentModality::InPerson,
            ]);
            $task = CrmTask::query()->create([
                'title' => 'Rappeler pour l’archet',
                'status' => CrmTaskStatus::Open,
                'due_at' => now()->setTime(14, 0),
            ]);

            return [$appointment, $task];
        });

        $this->actingAs($user)
            ->get(ClientAgenda::getUrl(tenant: $organization))
            ->assertOk()
            ->assertSeeText('Votre semaine de travail')
            ->assertSeeText('Dépôt du violon de Camille')
            ->assertSeeText('Rappeler pour l’archet')
            ->assertSee(AppointmentResource::getUrl('view', ['record' => $appointment]))
            ->assertSee(CrmTaskResource::getUrl('view', ['record' => $task]));
    }
}
