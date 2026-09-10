<?php

namespace App\Models;

use App\Enums\InstrumentAssetStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InstrumentAsset extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $instrument): void {
            if ($instrument->is_site_published && blank($instrument->public_slug)) {
                $instrument->public_slug = Str::slug($instrument->public_title ?: $instrument->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => InstrumentAssetStatus::class,
            'available_for_sale' => 'boolean',
            'available_for_rental' => 'boolean',
            'is_site_published' => 'boolean',
            'site_published_at' => 'immutable_datetime',
            'site_last_published_at' => 'immutable_datetime',
            'suggested_sale_amount' => 'decimal:2',
            'suggested_rental_amount' => 'decimal:2',
            'attributes' => 'array',
            'media' => 'array',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
