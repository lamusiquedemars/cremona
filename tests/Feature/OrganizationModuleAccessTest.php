<?php

namespace Tests\Feature;

use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
