<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalTier extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(InstrumentAsset::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(InstrumentCategory::class);
    }
}
