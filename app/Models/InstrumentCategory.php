<?php

namespace App\Models;

use App\Services\InstrumentRentalPricing;
use App\Services\RentalGridRateManager;
use App\Tenancy\Concerns\BelongsToOrganization;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentCategory extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            app(InstrumentRentalPricing::class)->assertCategoryDoesNotOverlap($category);
        });

        static::saved(function (self $category): void {
            app(InstrumentRentalPricing::class)->synchronizeInstrumentsForCategory($category);

            if ($category->wasRecentlyCreated || $category->wasChanged(['rental_monthly_amount', 'rental_price_effective_on'])) {
                app(RentalGridRateManager::class)->record($category);
            }
        });

        static::deleted(function (self $category): void {
            app(InstrumentRentalPricing::class)->synchronizeInstrumentsForCategory($category);
        });
    }

    protected function casts(): array
    {
        return [
            'rental_monthly_amount' => 'decimal:2',
            'rental_price_effective_on' => 'immutable_date',
            'eligible_sizes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(InstrumentAsset::class);
    }

    public function rentalTier(): BelongsTo
    {
        return $this->belongsTo(RentalTier::class);
    }

    public function priceVersions(): HasMany
    {
        return $this->hasMany(RentalPriceVersion::class, 'instrument_category_id');
    }

    public function monthlyAmountOn(?DateTimeInterface $date = null): float
    {
        $date ??= today();

        $amount = $this->priceVersions()->withoutGlobalScopes()
            ->whereDate('effective_on', '<=', $date)
            ->orderByDesc('effective_on')
            ->value('monthly_amount');

        return (float) ($amount ?? $this->rental_monthly_amount ?? 0);
    }
}
