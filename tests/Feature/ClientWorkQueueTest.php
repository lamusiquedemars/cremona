<?php

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationRole;
use App\Filament\Pages\ClientWorkQueue;
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

        app(OrganizationContext::class)->run($organization, function (): void {
            IncomingRequest::query()->create([
                'name_snapshot' => 'Camille Martin',
                'subject' => 'Essai d’archet',
                'message' => 'Puis-je prendre rendez-vous ?',
                'status' => IncomingRequestStatus::New,
                'received_at' => now(),
                'payload_fingerprint' => hash('sha256', 'client-work-request'),
            ]);
            Conversation::query()->create([
                'subject' => 'Question sur un violon',
                'status' => ConversationStatus::Open,
                'last_inbound_at' => now()->subMinute(),
            ]);
            CrmTask::query()->create([
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
            ->assertDontSeeText('Réponse déjà envoyée')
            ->assertDontSeeText('Autre organisation');
    }
}
