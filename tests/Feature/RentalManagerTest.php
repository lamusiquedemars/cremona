<?php

namespace Tests\Feature;

use App\Enums\InstrumentAssetStatus;
use App\Enums\RentalStatus;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\Rental;
use App\Services\RentalManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RentalManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rental_changes_the_instrument_availability_and_can_be_returned(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $instrument = InstrumentAsset::query()->create(['name' => 'Violon d’étude 4/4', 'available_for_rental' => true]);
            $rental = Rental::query()->create(['instrument_asset_id' => $instrument->id]);

            app(RentalManager::class)->activate($rental);
            $this->assertSame(RentalStatus::Active, $rental->fresh()->status);
            $this->assertSame(InstrumentAssetStatus::Rented, $instrument->fresh()->status);

            app(RentalManager::class)->return($rental);
            $this->assertSame(RentalStatus::Returned, $rental->fresh()->status);
            $this->assertSame(InstrumentAssetStatus::Available, $instrument->fresh()->status);
        });
    }

    public function test_an_unavailable_instrument_cannot_start_a_rental(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $instrument = InstrumentAsset::query()->create(['name' => 'Violon confié', 'available_for_rental' => false]);
            $rental = Rental::query()->create(['instrument_asset_id' => $instrument->id]);

            $this->expectException(LogicException::class);
            app(RentalManager::class)->activate($rental);
        });
    }
}
