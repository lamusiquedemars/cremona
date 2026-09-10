<?php

namespace App\Models;

use App\Enums\InstrumentAssetStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentAsset extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

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
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
