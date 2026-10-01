<?php

namespace Tests\Feature;

use App\Models\InstrumentAsset;
use App\Models\InstrumentCategory;
use App\Models\Organization;
use App\Models\Rental;
use App\Models\RentalTier;
use App\Services\InstrumentRentalPricing;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_an_instrument_is_assigned_the_single_matching_rental_grid(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $tier = RentalTier::query()->create(['name' => 'Étude']);
            $category = InstrumentCategory::query()->create([
                'name' => 'Violon enfant — étude',
                'family' => 'violon',
                'eligible_sizes' => ['1/8', '1/4'],
                'rental_tier_id' => $tier->id,
                'rental_monthly_amount' => 22,
            ]);
            $instrument = new InstrumentAsset([
                'name' => 'Violon enfant',
                'family' => 'violon',
                'rental_size' => '1/4',
                'rental_tier_id' => $tier->id,
                'available_for_rental' => true,
            ]);

            app(InstrumentRentalPricing::class)->applyToInstrument($instrument);
            $instrument->save();

            $this->assertSame($category->id, $instrument->instrument_category_id);
            $this->assertSame(22.0, $instrument->rentalMonthlyAmount());
        });
    }

    public function test_two_active_grids_cannot_cover_the_same_instrument_profile(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $tier = RentalTier::query()->create(['name' => 'Étude']);
            InstrumentCategory::query()->create([
                'name' => 'Violon enfant — étude',
                'family' => 'violon',
                'eligible_sizes' => ['1/4'],
                'rental_tier_id' => $tier->id,
                'rental_monthly_amount' => 22,
            ]);

            try {
                InstrumentCategory::query()->create([
                    'name' => 'Autre grille violon enfant',
                    'family' => 'violon',
                    'eligible_sizes' => ['1/4', '1/2'],
                    'rental_tier_id' => $tier->id,
                    'rental_monthly_amount' => 24,
                ]);
                $this->fail('Une grille en conflit aurait dû être refusée.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('eligible_sizes', $exception->errors());
            }
        });
    }

    public function test_an_exceptional_instrument_rate_takes_precedence_over_its_grid(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Violon exceptionnel',
                'available_for_rental' => true,
                'rental_pricing_mode' => 'override',
                'rental_amount_override' => 85,
            ]);
            $rental = Rental::query()->create(['instrument_asset_id' => $instrument->id]);

            $this->assertSame('85.00', $rental->fresh()->unit_amount);
        });
    }
}
