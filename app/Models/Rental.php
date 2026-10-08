<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Services\InstrumentRentalPricing;
use App\Services\RentalInsurancePricing;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Rental extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $rental): void {
            $rental->public_id ??= (string) Str::ulid();
            $rental->reference ??= 'LOC-'.str($rental->public_id)->substr(0, 8);
            if ((float) $rental->unit_amount === 0.0 && $rental->instrument_asset_id !== null) {
                $instrument = InstrumentAsset::query()->with('category')->find($rental->instrument_asset_id);

                if ($instrument?->available_for_rental
                    && $instrument->rental_pricing_mode !== 'override'
                    && (filled($instrument->family) || filled($instrument->rental_size) || filled($instrument->rental_tier_id))) {
                    app(InstrumentRentalPricing::class)->applyToInstrument($instrument);
                    if ($instrument->isDirty('instrument_category_id')) {
                        $instrument->save();
                    }
                    $instrument->load('category');
                }

                $rental->unit_amount = $instrument?->rentalMonthlyAmount() ?? 0;
            }
        });

        static::saving(function (self $rental): void {
            if ($rental->insurance_plan_id === null) {
                if ($rental->isDirty('insurance_plan_id')) {
                    $rental->insurance_plan_name = null;
                    $rental->insurance_clause_version = null;
                    $rental->insurance_coverage_summary = null;
                    $rental->insurance_monthly_amount = 0;
                }

                return;
            }

            $plan = RentalInsurancePlan::query()->find($rental->insurance_plan_id);
            $instrument = InstrumentAsset::query()->find($rental->instrument_asset_id);
            if ($plan === null || $instrument === null) {
                throw new \LogicException('La formule d’assurance et l’instrument doivent appartenir à l’organisation active.');
            }
            app(RentalInsurancePricing::class)->assertEligible($plan, $instrument);
            $rental->insurance_monthly_amount = $plan->monthly_amount;
            $rental->insurance_plan_name = $plan->name;
            $rental->insurance_clause_version = $plan->clause_version;
            $rental->insurance_coverage_summary = $plan->coverage_summary;
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => RentalStatus::class,
            'starts_on' => 'immutable_date',
            'expected_return_on' => 'immutable_date',
            'returned_on' => 'immutable_date',
            'unit_amount' => 'decimal:2',
            'insurance_monthly_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'returned_at' => 'immutable_datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(InstrumentAsset::class, 'instrument_asset_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function insurancePlan(): BelongsTo
    {
        return $this->belongsTo(RentalInsurancePlan::class);
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RentalDocument::class);
    }

    public function returnRecord(): HasOne
    {
        return $this->hasOne(RentalReturn::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
