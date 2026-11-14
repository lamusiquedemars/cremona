<?php

namespace App\Services;

use App\Models\InstrumentCategory;
use App\Models\Rental;
use App\Models\RentalPriceVersion;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class RentalGridRateManager
{
    public function record(InstrumentCategory $category): void
    {
        if ($category->rental_monthly_amount === null) {
            return;
        }

        $effectiveOn = CarbonImmutable::parse($category->rental_price_effective_on ?? today())->startOfDay();

        RentalPriceVersion::withoutGlobalScopes()->updateOrCreate([
            'instrument_category_id' => $category->getKey(),
            'effective_on' => $effectiveOn->toDateString(),
        ], [
            'organization_id' => $category->organization_id,
            'monthly_amount' => $category->rental_monthly_amount,
        ]);

        if ($effectiveOn->isPast() || $effectiveOn->isToday()) {
            $this->applyForCategory($category, $effectiveOn);
        }
    }

    public function applyDueRates(?DateTimeInterface $date = null): int
    {
        $date = CarbonImmutable::instance($date ?? now())->startOfDay();
        $updated = 0;

        InstrumentCategory::withoutGlobalScopes()
            ->eachById(function (InstrumentCategory $category) use ($date, &$updated): void {
                $updated += $this->applyForCategory($category, $date);
            });

        return $updated;
    }

    private function applyForCategory(InstrumentCategory $category, DateTimeInterface $date): int
    {
        $amount = $category->monthlyAmountOn($date);
        $instrumentIds = $category->instruments()->withoutGlobalScopes()->pluck('id');

        if ($instrumentIds->isEmpty()) {
            return 0;
        }

        return Rental::withoutGlobalScopes()
            ->whereIn('instrument_asset_id', $instrumentIds)
            ->where('rental_pricing_source', 'grid')
            ->update(['unit_amount' => $amount, 'updated_at' => now()]);
    }
}
