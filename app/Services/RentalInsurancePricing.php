<?php

namespace App\Services;

use App\Models\InstrumentAsset;
use App\Models\RentalInsurancePlan;
use Illuminate\Support\Collection;
use LogicException;

class RentalInsurancePricing
{
    /** @return Collection<int, RentalInsurancePlan> */
    public function eligiblePlans(InstrumentAsset $instrument): Collection
    {
        return RentalInsurancePlan::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (RentalInsurancePlan $plan): bool => $this->matches($plan, $instrument))
            ->values();
    }

    public function assertEligible(RentalInsurancePlan $plan, InstrumentAsset $instrument): void
    {
        if (! $plan->is_active || ! $this->matches($plan, $instrument)) {
            throw new LogicException('La formule d’assurance sélectionnée ne couvre pas cet instrument.');
        }
    }

    private function matches(RentalInsurancePlan $plan, InstrumentAsset $instrument): bool
    {
        if (filled($plan->family) && $plan->family !== $instrument->family) {
            return false;
        }
        if ($plan->rental_tier_id !== null && (int) $plan->rental_tier_id !== (int) $instrument->rental_tier_id) {
            return false;
        }

        $sizes = $plan->eligible_sizes ?? [];

        return $sizes === [] || in_array($instrument->rental_size, $sizes, true);
    }
}
