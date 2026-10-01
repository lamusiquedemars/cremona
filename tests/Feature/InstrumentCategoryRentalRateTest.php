<?php

namespace Tests\Feature;

use App\Models\InstrumentAsset;
use App\Models\InstrumentCategory;
use App\Models\Organization;
use App\Models\Rental;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstrumentCategoryRentalRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_category_monthly_rate_is_snapshotted_when_a_rental_is_created(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $category = InstrumentCategory::query()->create(['name' => 'Violon 4/4', 'rental_monthly_amount' => 42]);
            $instrument = InstrumentAsset::query()->create(['name' => 'Violon d’étude', 'instrument_category_id' => $category->id]);
            $rental = Rental::query()->create(['instrument_asset_id' => $instrument->id]);

            $this->assertSame('42.00', $rental->fresh()->unit_amount);

            $category->update(['rental_monthly_amount' => 49]);
            $this->assertSame('42.00', $rental->fresh()->unit_amount);
        });
    }
}
