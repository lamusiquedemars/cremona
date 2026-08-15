<?php

namespace Tests\Feature;

use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationRole;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\InboundChannels\InboundChannelResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Filament\Resources\People\Pages\ViewPerson;
use App\Filament\Resources\People\PersonResource;
use App\Filament\Resources\People\RelationManagers\CompaniesRelationManager;
use App\Models\Company;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\CrmRecordManager;
use App\Services\IncomingRequestManager;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertSee('Changer le statut')
            ->assertSee('Créer ou rattacher le contact');
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

    public function test_the_contact_page_combines_identity_coordinates_companies_and_requests(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);

        [$person, $request] = app(OrganizationContext::class)->run($organization, function (): array {
            $person = Person::query()->create([
                'first_name' => 'Camille',
                'last_name' => 'Martin',
                'display_name' => 'Camille Martin',
                'locale' => 'fr',
                'country_code' => 'FR',
            ]);
            $person->contactMethods()->create([
                'type' => 'email',
                'label' => 'Professionnel',
                'value' => 'camille@example.test',
                'is_primary' => true,
            ]);
            $company = Company::query()->create([
                'name' => 'Atelier Martin',
                'industry' => 'Artisanat',
            ]);
            $person->companies()->attach($company, [
                'organization_id' => $person->organization_id,
                'job_title' => 'Fondatrice',
                'is_primary' => true,
            ]);

            $manager = app(IncomingRequestManager::class);
            $request = $manager->receive([
                'subject' => 'Projet sur mesure',
                'message' => 'Une demande déjà rattachée au contact.',
            ]);
            $manager->linkPerson($request, $person);

            return [$person, $request];
        });

        $this->actingAs($viewer)
            ->get(PersonResource::getUrl('view', ['record' => $person], tenant: $organization))
            ->assertOk()
            ->assertSee('Camille Martin')
            ->assertSee('camille@example.test')
            ->assertSee('Projet sur mesure')
            ->assertDontSee('Modifier le contact');

        $this->actingAs($viewer)
            ->get(PersonResource::getUrl('view', [
                'record' => $person,
                'relation' => 'companies',
            ], tenant: $organization))
            ->assertOk()
            ->assertSee('Atelier Martin')
            ->assertSee('Fondatrice');

        $this->assertSame($person->id, $request->person_id);
    }

    public function test_the_company_page_combines_identity_coordinates_contacts_and_requests(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);

        [$company, $request] = app(OrganizationContext::class)->run($organization, function (): array {
            $company = Company::query()->create([
                'name' => 'Atelier Dupont',
                'legal_name' => 'Atelier Dupont SARL',
                'website' => 'https://atelier-dupont.example.test',
            ]);
            $company->contactMethods()->create([
                'type' => 'email',
                'label' => 'Accueil',
                'value' => 'bonjour@atelier-dupont.example.test',
                'is_primary' => true,
            ]);
            $person = Person::query()->create([
                'display_name' => 'Jeanne Dupont',
            ]);
            $company->people()->attach($person, [
                'organization_id' => $company->organization_id,
                'job_title' => 'Gérante',
                'is_primary' => true,
            ]);

            $manager = app(IncomingRequestManager::class);
            $request = $manager->receive([
                'subject' => 'Nouvelle vitrine',
                'message' => 'Une demande déjà rattachée à l’entreprise.',
            ]);
            $manager->linkCompany($request, $company);

            return [$company, $request];
        });

        $this->actingAs($viewer)
            ->get(CompanyResource::getUrl('view', ['record' => $company], tenant: $organization))
            ->assertOk()
            ->assertSee('Atelier Dupont SARL')
            ->assertSee('bonjour@atelier-dupont.example.test')
            ->assertSee('Jeanne Dupont')
            ->assertDontSee('Modifier l’entreprise');

        $this->actingAs($viewer)
            ->get(CompanyResource::getUrl('view', [
                'record' => $company,
                'relation' => 'requests',
            ], tenant: $organization))
            ->assertOk()
            ->assertSee('Nouvelle vitrine');

        $this->assertSame($company->id, $request->company_id);
        $this->assertNotNull($company->refresh()->last_activity_at);
    }

    public function test_global_search_finds_crm_records_and_keeps_tenants_isolated(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);

        app(OrganizationContext::class)->run($otherOrganization, function (): void {
            Person::query()->create(['display_name' => 'Intrus Recherche']);
        });

        [$person, $company, $request] = app(OrganizationContext::class)->run($organization, function (): array {
            $person = Person::query()->create(['display_name' => 'Camille Recherche']);
            $person->contactMethods()->create([
                'type' => 'email',
                'value' => 'camille.unique@example.test',
            ]);
            $company = Company::query()->create([
                'name' => 'Studio Recherche',
                'legal_name' => 'Studio Recherche SARL',
            ]);
            $company->contactMethods()->create([
                'type' => 'phone',
                'value' => '+33 1 44 55 66 77',
            ]);
            $request = app(IncomingRequestManager::class)->receive([
                'name_snapshot' => 'Camille Recherche',
                'subject' => 'Projet Recherche',
                'message' => 'Mot distinctif quartz-bleu.',
            ]);

            return [$person, $company, $request];
        });

        $this->actingAs($viewer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($organization, isQuiet: true);

        app(OrganizationContext::class)->run($organization, function () use ($person, $company, $request): void {
            $personResult = PersonResource::getGlobalSearchResults('camille.unique')->sole();
            $companyResult = CompanyResource::getGlobalSearchResults('44 55 66')->sole();
            $requestResult = IncomingRequestResource::getGlobalSearchResults('quartz-bleu')->sole();

            $this->assertSame($person->display_name, $personResult->title);
            $this->assertStringContainsString((string) $person->getRouteKey(), $personResult->url);
            $this->assertSame('camille.unique@example.test', $personResult->details['Coordonnées']);
            $this->assertSame($company->name, $companyResult->title);
            $this->assertStringContainsString((string) $company->getRouteKey(), $companyResult->url);
            $this->assertSame('Projet Recherche', $requestResult->title);
            $this->assertStringContainsString((string) $request->getRouteKey(), $requestResult->url);
            $this->assertCount(1, PersonResource::getGlobalSearchResults('Recherche'));
        });
    }

    public function test_contact_notes_are_visible_to_viewers_but_only_managers_can_add_them(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $collaborator = User::factory()->create();
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        $person = app(OrganizationContext::class)->run($organization, function () use ($collaborator): Person {
            $person = Person::query()->create(['display_name' => 'Contact avec note']);
            $person->notes()->create([
                'author_user_id' => $collaborator->id,
                'body' => 'Souhaite être rappelé mardi matin.',
            ]);

            return $person;
        });

        $url = PersonResource::getUrl('view', [
            'record' => $person,
            'relation' => 'notes',
        ], tenant: $organization);

        $this->actingAs($viewer)
            ->get($url)
            ->assertOk()
            ->assertSee('Souhaite être rappelé mardi matin.')
            ->assertDontSee('Ajouter une note');

        $this->actingAs($collaborator)
            ->get($url)
            ->assertOk()
            ->assertSee('Souhaite être rappelé mardi matin.')
            ->assertSee('Ajouter une note');
    }

    public function test_contact_company_links_can_only_be_managed_by_collaborators(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $collaborator = User::factory()->create();
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        [$person, $company] = app(OrganizationContext::class)->run($organization, fn (): array => [
            Person::query()->create(['display_name' => 'Contact à rattacher']),
            Company::query()->create(['name' => 'Entreprise à rattacher']),
        ]);

        $personCompaniesUrl = PersonResource::getUrl('view', [
            'record' => $person,
            'relation' => 'companies',
        ], tenant: $organization);
        $companyContactsUrl = CompanyResource::getUrl('view', [
            'record' => $company,
            'relation' => 'contacts',
        ], tenant: $organization);

        $this->actingAs($viewer)
            ->get($personCompaniesUrl)
            ->assertOk()
            ->assertDontSee('Rattacher une entreprise');
        $this->actingAs($viewer)
            ->get($companyContactsUrl)
            ->assertOk()
            ->assertDontSee('Rattacher un contact');

        $this->actingAs($collaborator)
            ->get($personCompaniesUrl)
            ->assertOk()
            ->assertSee('Rattacher une entreprise');
        $this->actingAs($collaborator)
            ->get($companyContactsUrl)
            ->assertOk()
            ->assertSee('Rattacher un contact');
    }

    public function test_a_collaborator_can_link_an_existing_company_to_a_contact(): void
    {
        $organization = Organization::factory()->create();
        $collaborator = User::factory()->create();
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        [$person, $company] = app(OrganizationContext::class)->run($organization, fn (): array => [
            Person::query()->create(['display_name' => 'Camille Relation']),
            Company::query()->create(['name' => 'Atelier Relation']),
        ]);

        $this->actingAs($collaborator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($organization, isQuiet: true);

        app(OrganizationContext::class)->run($organization, function () use ($person, $company): void {
            Livewire::test(CompaniesRelationManager::class, [
                'ownerRecord' => $person,
                'pageClass' => ViewPerson::class,
            ])->callTableAction('attach', data: [
                'recordId' => $company->id,
                'job_title' => 'Responsable atelier',
                'is_primary' => true,
            ])->assertHasNoTableActionErrors();

            $this->assertDatabaseHas('company_person', [
                'organization_id' => $person->organization_id,
                'person_id' => $person->id,
                'company_id' => $company->id,
                'job_title' => 'Responsable atelier',
                'is_primary' => true,
            ]);
            $this->assertNotNull($person->refresh()->last_activity_at);
            $this->assertNotNull($company->refresh()->last_activity_at);
        });
    }

    public function test_archived_crm_records_are_read_only_until_reactivated(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $collaborator = User::factory()->create();
        $viewer->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
        ]);
        $collaborator->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
        ]);

        $person = app(OrganizationContext::class)->run(
            $organization,
            fn () => Person::query()->create(['display_name' => 'Contact à archiver']),
        );
        $viewUrl = PersonResource::getUrl('view', ['record' => $person], tenant: $organization);

        $this->actingAs($viewer)
            ->get($viewUrl)
            ->assertOk()
            ->assertDontSee('Archiver');

        $this->actingAs($collaborator)
            ->get($viewUrl)
            ->assertOk()
            ->assertSee('Archiver')
            ->assertDontSee('Réactiver');

        app(OrganizationContext::class)->run(
            $organization,
            fn () => app(CrmRecordManager::class)->archive($person),
        );

        $this->actingAs($collaborator)
            ->get($viewUrl)
            ->assertOk()
            ->assertSee('Réactiver')
            ->assertDontSee('Modifier le contact')
            ->assertDontSee('Archiver');
        $this->actingAs($collaborator)
            ->get(PersonResource::getUrl('edit', ['record' => $person], tenant: $organization))
            ->assertForbidden();
    }

    public function test_only_integration_managers_can_see_inbound_channels(): void
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

        app(OrganizationContext::class)->run($organization, function (): void {
            $manager = app(OrganizationIntegrationManager::class);
            $manager->createApiToken('maracuja_cms', 'site-principal');
            $manager->create('brevo', 'transactionnel', ['api_key' => 'secret']);
        });

        $this->actingAs($administrator)
            ->get(InboundChannelResource::getUrl('index', tenant: $organization))
            ->assertOk()
            ->assertSee('site-principal')
            ->assertSee('Nouveau canal')
            ->assertDontSee('transactionnel');

        $this->actingAs($collaborator)
            ->get(InboundChannelResource::getUrl('index', tenant: $organization))
            ->assertForbidden();
    }
}
