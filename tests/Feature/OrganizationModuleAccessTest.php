<?php

namespace Tests\Feature;

use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\People\PersonResource;
use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationModuleRegistry;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organization_without_modules_cannot_access_or_see_business_resources(): void
    {
        $organization = Organization::factory()->create(['slug' => 'marcos-tulio-advocacia']);
        $organization->modules()->delete();
        $user = User::factory()->platformAdministrator()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('crm'));
            $this->assertFalse(PersonResource::shouldRegisterNavigation());
            $this->assertFalse(InstrumentAssetResource::shouldRegisterNavigation());
            $this->assertFalse(PersonResource::canGloballySearch());
        });

        $this->actingAs($user)
            ->get(PersonResource::getUrl('index', tenant: $organization))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(InstrumentAssetResource::getUrl('index', tenant: $organization))
            ->assertForbidden();
    }

    public function test_an_enabled_module_only_unlocks_its_own_resources(): void
    {
        $organization = Organization::factory()->create();
        $organization->modules()->delete();
        $this->actingAs(User::factory()->platformAdministrator()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationModule::query()->create(['module' => 'crm', 'enabled' => true]);

            $this->assertTrue(app(OrganizationModuleAccess::class)->enabled('crm'));
            $this->assertTrue(PersonResource::shouldRegisterNavigation());
            $this->assertTrue(PersonResource::canGloballySearch());
            $this->assertFalse(InstrumentAssetResource::shouldRegisterNavigation());
        });
    }

    public function test_a_disabled_module_never_unlocks_a_resource(): void
    {
        $organization = Organization::factory()->create();
        $organization->modules()->delete();

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationModule::query()->create(['module' => 'luthier_catalog', 'enabled' => false]);

            $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('luthier_catalog'));
            $this->assertFalse(InstrumentAssetResource::shouldRegisterNavigation());
        });
    }

    public function test_the_module_registry_persists_the_selection_without_deleting_existing_data(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'luthier_catalog']);

        $this->assertSame(['crm', 'luthier_catalog'], app(OrganizationModuleRegistry::class)->enabledFor($organization));
        $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('crm'));

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->assertTrue(app(OrganizationModuleAccess::class)->enabled('crm'));
            $this->assertTrue(app(OrganizationModuleAccess::class)->enabled('luthier_catalog'));
            $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('workshop'));
        });
    }

    public function test_grouped_modules_keep_their_unique_technical_keys(): void
    {
        $groups = app(OrganizationModuleRegistry::class)->grouped();

        $this->assertArrayHasKey('crm', $groups['customer_follow_up']['modules']);
        $this->assertArrayHasKey('appointments', $groups['customer_follow_up']['modules']);
        $this->assertArrayHasKey('luthier_catalog', $groups['workshop']['modules']);
        $this->assertArrayNotHasKey(0, $groups['customer_follow_up']['modules']);
        $this->assertArrayNotHasKey(0, $groups['workshop']['modules']);
    }

    public function test_workshop_automatically_enables_its_catalog_dependency(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleRegistry::class)->sync($organization, ['workshop']);

        $this->assertSame(['luthier_catalog', 'workshop'], app(OrganizationModuleRegistry::class)->enabledFor($organization));
    }

    public function test_luthier_pack_defines_its_operational_modules_and_menu_names(): void
    {
        $preset = app(OrganizationModuleRegistry::class)->preset('luthier');

        $this->assertSame([
            'crm',
            'appointments',
            'quotes',
            'luthier_catalog',
            'workshop',
            'rentals',
            'inventory',
        ], $preset['modules']);
        $this->assertSame('Clients', $preset['labels']['customer_follow_up']);
        $this->assertSame('Instruments et prestations', $preset['labels']['luthier_catalog']);
    }

    public function test_no_pack_defines_a_generic_operational_preset(): void
    {
        $preset = app(OrganizationModuleRegistry::class)->preset(null);

        $this->assertSame([
            'crm',
            'appointments',
            'quotes',
            'marketing',
        ], $preset['modules']);
        $this->assertSame('Relation client', $preset['labels']['customer_follow_up']);
    }

    public function test_pack_selector_updates_module_toggles_immediately(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        $organization = Organization::factory()->create([
            'settings' => ['timezone' => 'Europe/Paris'],
        ]);
        $organization->modules()->delete();
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::actingAs($administrator)
            ->test(EditOrganization::class, ['record' => $organization->getRouteKey()])
            ->fillForm(['vertical_pack' => 'luthier'])
            ->assertFormSet([
                'modules.luthier_catalog' => true,
                'modules.workshop' => true,
                'modules.rentals' => true,
                'settings.presentation.labels.customer_follow_up' => 'Clients',
                'settings.presentation.labels.luthier_catalog' => 'Instruments et prestations',
            ])
            ->assertFormFieldIsDisabled('modules.luthier_catalog')
            ->fillForm(['vertical_pack' => '__none__'])
            ->assertFormSet([
                'modules.luthier_catalog' => false,
                'modules.workshop' => false,
                'modules.rentals' => false,
                'modules.marketing' => true,
                'settings.presentation.labels.customer_follow_up' => 'Relation client',
            ])
            ->fillForm(['modules.workshop' => true])
            ->assertFormSet(['modules.luthier_catalog' => true])
            ->assertFormFieldIsDisabled('modules.luthier_catalog');
    }

    public function test_a_platform_administrator_can_create_an_organization_without_a_business_pack(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::actingAs($administrator)
            ->test(CreateOrganization::class)
            ->fillForm([
                'name' => 'Marcos Túlio Advocacia',
                'slug' => 'marcos-tulio-advocacia',
                'vertical_pack' => '__none__',
                'status' => 'active',
                'settings.timezone' => 'America/Cuiaba',
                'modules' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('organizations', [
            'slug' => 'marcos-tulio-advocacia',
            'vertical_pack' => null,
        ]);
    }

    public function test_a_platform_administrator_can_remove_an_existing_business_pack(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        $organization = Organization::factory()->create([
            'vertical_pack' => 'luthier',
            'settings' => ['timezone' => 'America/Cuiaba'],
        ]);
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::actingAs($administrator)
            ->test(EditOrganization::class, ['record' => $organization->getRouteKey()])
            ->assertFormSet([
                'vertical_pack' => 'luthier',
            ])
            ->fillForm([
                'vertical_pack' => '__none__',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($organization->refresh()->vertical_pack);
    }
}
