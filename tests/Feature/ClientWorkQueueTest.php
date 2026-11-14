<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationRole;
use App\Filament\Pages\ClientWorkQueue;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationModuleRegistry;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientWorkQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_client_work_queue_combines_actionable_requests_messages_and_tasks_for_the_active_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm']);
        app(OrganizationModuleRegistry::class)->sync($otherOrganization, ['crm']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        $records = app(OrganizationContext::class)->run($organization, function (): array {
            $request = IncomingRequest::query()->create([
                'name_snapshot' => 'Camille Martin',
                'subject' => 'Essai d’archet',
                'message' => 'Puis-je prendre rendez-vous ?',
                'status' => IncomingRequestStatus::New,
                'received_at' => now(),
                'payload_fingerprint' => hash('sha256', 'client-work-request'),
            ]);
            $conversation = Conversation::query()->create([
                'subject' => 'Question sur un violon',
                'status' => ConversationStatus::Open,
                'last_inbound_at' => now()->subMinute(),
            ]);
            $task = CrmTask::query()->create([
                'title' => 'Rappeler après l’essai',
                'status' => CrmTaskStatus::Open,
                'due_at' => now()->subHour(),
            ]);
            Conversation::query()->create([
                'subject' => 'Réponse déjà envoyée',
                'status' => ConversationStatus::Open,
                'last_inbound_at' => now()->subHours(2),
                'last_outbound_at' => now()->subHour(),
            ]);

            return [$request, $conversation, $task];
        });

        app(OrganizationContext::class)->run($otherOrganization, function (): void {
            IncomingRequest::query()->create([
                'name_snapshot' => 'Autre organisation',
                'message' => 'Cette demande ne doit pas apparaître.',
                'status' => IncomingRequestStatus::New,
                'received_at' => now(),
                'payload_fingerprint' => hash('sha256', 'other-client-work-request'),
            ]);
        });

        $this->actingAs($user)
            ->get(ClientWorkQueue::getUrl(tenant: $organization))
            ->assertOk()
            ->assertSeeText('Essai d’archet')
            ->assertSeeText('Question sur un violon')
            ->assertSeeText('Rappeler après l’essai')
            ->assertSee(IncomingRequestResource::getUrl('view', ['record' => $records[0]->public_id]))
            ->assertSee(ConversationResource::getUrl('view', ['record' => $records[1]->public_id]))
            ->assertSee(CrmTaskResource::getUrl('view', ['record' => $records[2]->public_id]))
            ->assertDontSeeText('Réponse déjà envoyée')
            ->assertDontSeeText('Autre organisation');
    }

    public function test_the_client_work_queue_shows_a_next_page_link_when_more_than_twenty_five_items_are_open(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        app(OrganizationContext::class)->run($organization, function (): void {
            foreach (range(1, 26) as $index) {
                IncomingRequest::query()->create([
                    'subject' => "Demande {$index}",
                    'message' => 'À traiter.',
                    'status' => IncomingRequestStatus::New,
                    'received_at' => now()->subMinutes($index),
                    'payload_fingerprint' => hash('sha256', "client-work-page-{$index}"),
                ]);
            }
        });

        $this->actingAs($user)
            ->get(ClientWorkQueue::getUrl(tenant: $organization))
            ->assertOk()
            ->assertSeeText('Page 1 sur 2')
            ->assertSeeText('Suivant')
            ->assertSee('clientWorkPage=2');
    }

    public function test_the_client_work_queue_includes_overdue_and_imminent_appointments_but_not_distant_ones(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'appointments']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        $appointments = app(OrganizationContext::class)->run($organization, function (): array {
            $overdue = Appointment::query()->create([
                'title' => 'Rappeler après un essai',
                'status' => AppointmentStatus::Scheduled,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->subDay()->addHour(),
                'timezone' => 'Europe/Paris',
                'modality' => 'phone',
            ]);
            $imminent = Appointment::query()->create([
                'title' => 'Essai d’alto',
                'status' => AppointmentStatus::Scheduled,
                'starts_at' => now()->addDays(3),
                'ends_at' => now()->addDays(3)->addHour(),
                'timezone' => 'Europe/Paris',
                'modality' => 'in_person',
            ]);
            Appointment::query()->create([
                'title' => 'Rendez-vous du mois prochain',
                'status' => AppointmentStatus::Scheduled,
                'starts_at' => now()->addDays(8),
                'ends_at' => now()->addDays(8)->addHour(),
                'timezone' => 'Europe/Paris',
                'modality' => 'video',
            ]);

            return [$overdue, $imminent];
        });

        $this->actingAs($user)
            ->get(ClientWorkQueue::getUrl(tenant: $organization))
            ->assertOk()
            ->assertSeeText('Rappeler après un essai')
            ->assertSeeText('Essai d’alto')
            ->assertSeeText('Rendez-vous à préparer')
            ->assertSee(AppointmentResource::getUrl('view', ['record' => $appointments[0]->public_id]))
            ->assertSee(AppointmentResource::getUrl('view', ['record' => $appointments[1]->public_id]))
            ->assertDontSeeText('Rendez-vous du mois prochain');
    }
}
