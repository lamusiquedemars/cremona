<?php

namespace App\Models;

use App\Services\InstrumentRentalPricing;
use App\Tenancy\Concerns\BelongsToOrganization;
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
        });

        static::deleted(function (self $category): void {
            app(InstrumentRentalPricing::class)->synchronizeInstrumentsForCategory($category);
        });
    }

    protected function casts(): array
    {
        return [
            'rental_monthly_amount' => 'decimal:2',
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
}
