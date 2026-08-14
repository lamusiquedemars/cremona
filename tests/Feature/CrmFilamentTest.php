<?php

namespace Tests\Feature;

use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationRole;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\IncomingRequestManager;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_viewer_can_open_crm_lists_but_cannot_open_creation_pages(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);

        $this->actingAs($viewer)
            ->get(PersonResource::getUrl('index', tenant: $organization))
            ->assertOk();
        $this->actingAs($viewer)
            ->get(CompanyResource::getUrl('index', tenant: $organization))
            ->assertOk();
        $this->actingAs($viewer)
            ->get(IncomingRequestResource::getUrl('index', tenant: $organization))
            ->assertOk();
        $this->actingAs($viewer)
            ->get(PersonResource::getUrl('create', tenant: $organization))
            ->assertForbidden();
        $this->actingAs($viewer)
            ->get(CompanyResource::getUrl('create', tenant: $organization))
            ->assertForbidden();
    }

    public function test_a_collaborator_can_open_contact_and_company_creation_pages(): void
    {
        $organization = Organization::factory()->create();
        $collaborator = User::factory()->create();
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        $this->actingAs($collaborator)
            ->get(PersonResource::getUrl('create', tenant: $organization))
            ->assertOk();
        $this->actingAs($collaborator)
            ->get(CompanyResource::getUrl('create', tenant: $organization))
            ->assertOk();
    }

    public function test_a_request_detail_renders_its_snapshot_and_workflow_actions(): void
    {
        $organization = Organization::factory()->create();
        $collaborator = User::factory()->create();
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        $request = app(OrganizationContext::class)->run(
            $organization,
            fn () => app(IncomingRequestManager::class)->receive([
                'name_snapshot' => 'Camille Martin',
                'email_snapshot' => 'camille@example.test',
                'subject' => 'Demande de rendez-vous',
                'message' => 'Je souhaite échanger au sujet de mon projet.',
            ]),
        );

        $this->actingAs($collaborator)
            ->get(IncomingRequestResource::getUrl('view', ['record' => $request], tenant: $organization))
            ->assertOk()
            ->assertSee('Camille Martin')
            ->assertSee('Demande de rendez-vous')
            ->assertSee('Changer le statut');
    }

    public function test_the_dashboard_and_work_queues_reflect_active_requests(): void
    {
        $organization = Organization::factory()->create();
        $collaborator = User::factory()->create();
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        app(OrganizationContext::class)->run($organization, function () use ($collaborator): void {
            $manager = app(IncomingRequestManager::class);
            $manager->receive([
                'name_snapshot' => 'Sans responsable',
                'subject' => 'Demande non attribuée',
                'message' => 'Cette demande doit apparaître dans la file non attribuée.',
            ]);
            $assigned = $manager->receive([
                'name_snapshot' => 'Contact attribué',
                'subject' => 'Demande attribuée',
                'message' => 'Cette demande appartient au collaborateur.',
            ]);
            $manager->assign($assigned, $collaborator, $collaborator);
            $manager->transition($assigned, IncomingRequestStatus::InProgress, actor: $collaborator);
        });

        $this->actingAs($collaborator)
            ->get(Filament::getPanel('admin')->getUrl($organization))
            ->assertOk()
            ->assertSee('Relation client')
            ->assertSee('Demandes à traiter')
            ->assertSee('Demande non attribuée')
            ->assertSee('Demande attribuée');

        $this->actingAs($collaborator)
            ->get(IncomingRequestResource::getUrl('index', ['tab' => 'unassigned'], tenant: $organization))
            ->assertOk()
            ->assertSee('Demande non attribuée')
            ->assertDontSee('Demande attribuée');
    }
}
