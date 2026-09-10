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
        $user = User::factory()->platformAdministrator()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('contacts'));
            $this->assertFalse(PersonResource::shouldRegisterNavigation());
            $this->assertFalse(InstrumentAssetResource::shouldRegisterNavigation());
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

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationModule::query()->create(['module' => 'contacts', 'enabled' => true]);

            $this->assertTrue(app(OrganizationModuleAccess::class)->enabled('contacts'));
            $this->assertTrue(PersonResource::shouldRegisterNavigation());
            $this->assertFalse(InstrumentAssetResource::shouldRegisterNavigation());
        });
    }

    public function test_a_disabled_module_never_unlocks_a_resource(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationModule::query()->create(['module' => 'instruments', 'enabled' => false]);

            $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('instruments'));
            $this->assertFalse(InstrumentAssetResource::shouldRegisterNavigation());
        });
    }

    public function test_the_module_registry_persists_the_selection_without_deleting_existing_data(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleRegistry::class)->sync($organization, ['contacts', 'instruments']);

        $this->assertSame(['contacts', 'instruments'], app(OrganizationModuleRegistry::class)->enabledFor($organization));
        $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('contacts'));

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->assertTrue(app(OrganizationModuleAccess::class)->enabled('contacts'));
            $this->assertTrue(app(OrganizationModuleAccess::class)->enabled('instruments'));
            $this->assertFalse(app(OrganizationModuleAccess::class)->enabled('interventions'));
        });
    }

    public function test_grouped_modules_keep_their_unique_technical_keys(): void
    {
        $groups = app(OrganizationModuleRegistry::class)->grouped();

        $this->assertArrayHasKey('contacts', $groups['relation_client']['modules']);
        $this->assertArrayHasKey('appointments', $groups['organisation']['modules']);
        $this->assertArrayHasKey('instruments', $groups['offer']['modules']);
        $this->assertArrayNotHasKey(0, $groups['relation_client']['modules']);
        $this->assertArrayNotHasKey(0, $groups['organisation']['modules']);
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
                'vertical_pack' => null,
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
        $organization = Organization::factory()->create(['vertical_pack' => 'luthier']);
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::actingAs($administrator)
            ->test(EditOrganization::class, ['record' => $organization->getRouteKey()])
            ->fillForm([
                'vertical_pack' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($organization->refresh()->vertical_pack);
    }
}
