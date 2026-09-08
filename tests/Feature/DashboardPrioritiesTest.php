<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CampaignStatus;
use App\Enums\ConversationStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationRole;
use App\Enums\QuoteStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Organization;
use App\Models\Quote;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardPrioritiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_summarizes_todays_priorities_in_the_organization_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-09-08 10:00:00', 'UTC'));

        $organization = Organization::factory()->create([
            'settings' => ['timezone' => 'Europe/Paris'],
        ]);
        $collaborator = User::factory()->create();
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        app(OrganizationContext::class)->run($organization, function (): void {
            IncomingRequest::query()->create([
                'name_snapshot' => 'Nouvelle demande prioritaire',
                'message' => 'Demande à traiter depuis le tableau de bord.',
                'status' => IncomingRequestStatus::New,
                'received_at' => now()->subHour(),
                'payload_fingerprint' => hash('sha256', 'dashboard-priority-request'),
            ]);
            Conversation::query()->create([
                'subject' => 'Réponse client attendue',
                'status' => ConversationStatus::Open,
                'last_inbound_at' => now()->subHour(),
            ]);
            Conversation::query()->create([
                'subject' => 'Réponse déjà envoyée',
                'status' => ConversationStatus::Open,
                'last_inbound_at' => now()->subHours(2),
                'last_outbound_at' => now()->subHour(),
            ]);
            CrmTask::query()->create([
                'title' => 'Relance en retard',
                'status' => CrmTaskStatus::Open,
                'due_at' => now()->subHour(),
            ]);
            Appointment::query()->create([
                'title' => 'Rendez-vous du jour',
                'status' => AppointmentStatus::Scheduled,
                'starts_at' => now()->addHour(),
                'ends_at' => now()->addHours(2),
            ]);
            Quote::query()->create([
                'reference' => 'DASH-001',
                'title' => 'Devis expiré à relancer',
                'status' => QuoteStatus::Sent,
                'valid_until' => '2026-09-07',
            ]);
            Campaign::query()->create([
                'name' => 'Campagne à vérifier',
                'channel' => 'google_ads',
                'tracking_key' => 'priority-campaign',
                'external_reference' => '123456789',
                'status' => CampaignStatus::Active,
                'google_ads_synced_at' => now()->subDays(2),
            ]);
        });

        $this->actingAs($collaborator)
            ->get(Filament::getPanel('admin')->getUrl($organization))
            ->assertOk()
            ->assertSeeText('Priorités du jour')
            ->assertSeeTextInOrder(['Nouvelles demandes', '1', '1 non lue'])
            ->assertSeeTextInOrder(['Correspondances à traiter', '1', 'Dernier message reçu'])
            ->assertSeeTextInOrder(['Tâches à échéance', '1', '1 en retard'])
            ->assertSeeTextInOrder(['Rendez-vous aujourd’hui', '1', 'À venir'])
            ->assertSeeTextInOrder(['Devis à suivre', '1', '1 hors validité'])
            ->assertSeeTextInOrder(['Campagnes à vérifier', '1', 'Google Ads · synchro 06/09 12:00'])
            ->assertSeeText('Cremona · 12:00 · Europe/Paris');
    }

    public function test_priority_links_open_prefiltered_work_queues(): void
    {
        $this->travelTo(Carbon::parse('2026-09-08 10:00:00', 'UTC'));

        $organization = Organization::factory()->create([
            'settings' => ['timezone' => 'Europe/Paris'],
        ]);
        $collaborator = User::factory()->create();
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        app(OrganizationContext::class)->run($organization, function (): void {
            CrmTask::query()->create([
                'title' => 'Tâche due aujourd’hui',
                'due_at' => now()->addHour(),
            ]);
            CrmTask::query()->create([
                'title' => 'Tâche future',
                'due_at' => now()->addDays(2),
            ]);
            Appointment::query()->create([
                'title' => 'Rendez-vous aujourd’hui',
                'starts_at' => now()->addHour(),
                'ends_at' => now()->addHours(2),
            ]);
            Appointment::query()->create([
                'title' => 'Rendez-vous futur',
                'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(2)->addHour(),
            ]);
            Quote::query()->create([
                'reference' => 'DASH-002',
                'title' => 'Devis envoyé',
                'status' => QuoteStatus::Sent,
            ]);
            Quote::query()->create([
                'reference' => 'DASH-003',
                'title' => 'Devis brouillon',
                'status' => QuoteStatus::Draft,
            ]);
            Campaign::query()->create([
                'name' => 'Campagne obsolète',
                'channel' => 'google_ads',
                'tracking_key' => 'stale-campaign',
                'external_reference' => '111',
                'status' => CampaignStatus::Active,
                'google_ads_synced_at' => now()->subDays(2),
            ]);
            Campaign::query()->create([
                'name' => 'Campagne fraîche',
                'channel' => 'google_ads',
                'tracking_key' => 'fresh-campaign',
                'external_reference' => '222',
                'status' => CampaignStatus::Active,
                'google_ads_synced_at' => now()->subHour(),
            ]);
        });

        $this->actingAs($collaborator)
            ->get(CrmTaskResource::getUrl('index', ['tab' => 'due'], tenant: $organization))
            ->assertOk()
            ->assertSeeText('Tâche due aujourd’hui')
            ->assertDontSeeText('Tâche future');
        $this->actingAs($collaborator)
            ->get(AppointmentResource::getUrl('index', ['tab' => 'today'], tenant: $organization))
            ->assertOk()
            ->assertSeeText('Rendez-vous aujourd’hui')
            ->assertDontSeeText('Rendez-vous futur');
        $this->actingAs($collaborator)
            ->get(QuoteResource::getUrl('index', ['tab' => 'follow_up'], tenant: $organization))
            ->assertOk()
            ->assertSeeText('Devis envoyé')
            ->assertDontSeeText('Devis brouillon');
        $this->actingAs($collaborator)
            ->get(CampaignResource::getUrl('index', ['tab' => 'attention'], tenant: $organization))
            ->assertOk()
            ->assertSeeText('Campagne obsolète')
            ->assertDontSeeText('Campagne fraîche');
    }
}
