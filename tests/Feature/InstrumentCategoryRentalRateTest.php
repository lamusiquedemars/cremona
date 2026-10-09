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

    public function test_switching_back_to_the_grid_clears_a_legacy_exception_and_uses_the_grid_rate(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $tier = RentalTier::query()->create(['name' => 'Étude']);
            $category = InstrumentCategory::query()->create([
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
                'rental_pricing_mode' => 'override',
                'rental_amount_override' => 45,
            ]);

            $instrument->update(['rental_pricing_mode' => 'automatic']);

            $instrument->refresh();
            $this->assertSame($category->id, $instrument->instrument_category_id);
            $this->assertNull($instrument->rental_amount_override);
            $this->assertSame(38.0, $instrument->load('category')->rentalMonthlyAmount());

            $rental = Rental::query()->create(['instrument_asset_id' => $instrument->id]);
            $this->assertSame('38.00', $rental->fresh()->unit_amount);
        });
    }

    public function test_a_legacy_rental_instrument_can_be_saved_before_it_is_qualified(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Alto d’étude',
                'family' => 'alto',
                'available_for_rental' => true,
            ]);

            $instrument->description = 'Fiche existante, à qualifier plus tard.';
            app(InstrumentRentalPricing::class)->applyToInstrument($instrument);
            $instrument->save();

            $this->assertSame('Fiche existante, à qualifier plus tard.', $instrument->fresh()->description);
            $this->assertNull($instrument->fresh()->instrument_category_id);
        });
    }

    public function test_a_legacy_qualified_instrument_can_be_saved_before_its_grid_exists(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $tier = RentalTier::query()->create(['name' => 'Étude']);
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Alto d’étude',
                'family' => 'alto',
                'rental_size' => '16in',
                'rental_tier_id' => $tier->id,
                'available_for_rental' => true,
            ]);

            $instrument->description = 'Fiche conservée avant la création des grilles.';
            app(InstrumentRentalPricing::class)->applyToInstrument($instrument);
            $instrument->save();

            $this->assertSame('Fiche conservée avant la création des grilles.', $instrument->fresh()->description);
            $this->assertNull($instrument->fresh()->instrument_category_id);
        });
    }

    public function test_creating_a_grid_attaches_already_qualified_instruments_without_reopening_them(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $tier = RentalTier::query()->create(['name' => 'Étude']);
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Alto d’étude',
                'family' => 'alto',
                'rental_size' => '16in',
                'rental_tier_id' => $tier->id,
                'available_for_rental' => true,
            ]);

            $this->assertNull($instrument->instrument_category_id);

            $category = InstrumentCategory::query()->create([
                'name' => 'Alto adulte — étude',
                'family' => 'alto',
                'eligible_sizes' => ['16in'],
                'rental_tier_id' => $tier->id,
                'rental_monthly_amount' => 38,
            ]);

            $this->assertSame($category->id, $instrument->fresh()->instrument_category_id);
        });
    }

    public function test_a_recorded_rental_amount_is_not_replaced_until_the_user_chooses_another_price(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $instrument = InstrumentAsset::query()->create(['name' => 'Alto existant']);
            $rental = Rental::query()->create([
                'instrument_asset_id' => $instrument->id,
                'unit_amount' => 45,
                'rental_pricing_source' => 'recorded',
            ]);

            $rental->update(['notes' => 'Montant historique conservé.']);

            $this->assertSame('45.00', $rental->fresh()->unit_amount);
            $this->assertSame('recorded', $rental->fresh()->rental_pricing_source);
        });
    }
}
