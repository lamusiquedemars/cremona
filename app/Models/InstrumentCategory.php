<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentCategory extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            app(\App\Services\InstrumentRentalPricing::class)->assertCategoryDoesNotOverlap($category);
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
