<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Filament\Resources\Rentals\Pages\CreateRental;
use App\Models\InstrumentAsset;
use App\Models\InstrumentCategory;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Rental;
use App\Models\RentalTier;
use App\Models\User;
use App\Services\OrganizationModuleRegistry;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentalFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_collaborator_can_create_a_rental_with_the_monthly_rate_from_the_instrument_grid(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'luthier_catalog', 'rentals']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        [$instrument, $person] = app(OrganizationContext::class)->run($organization, function (): array {
            $tier = RentalTier::query()->create(['name' => 'Étude']);
            InstrumentCategory::query()->create([
                'name' => 'Alto adulte — étude',
                'family' => 'alto',
                'eligible_sizes' => ['16in'],
                'rental_tier_id' => $tier->id,
                'rental_monthly_amount' => 38,
            ]);
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Alto d’étude',
                'family' => 'alto',
                'rental_size' => '16in',
                'rental_tier_id' => $tier->id,
                'available_for_rental' => true,
            ]);

            return [$instrument, Person::query()->create(['display_name' => 'Camille Durand'])];
        });

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($organization, isQuiet: true);

        app(OrganizationContext::class)->run($organization, function () use ($instrument, $person): void {
            Livewire::test(CreateRental::class)
                ->fillForm([
                    'instrument_asset_id' => $instrument->id,
                    'person_id' => $person->id,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $rental = Rental::query()->sole();
            $this->assertSame($instrument->id, $rental->instrument_asset_id);
            $this->assertSame($person->id, $rental->person_id);
            $this->assertSame('grid', $rental->rental_pricing_source);
            $this->assertSame('38.00', $rental->unit_amount);
        });
    }
}
