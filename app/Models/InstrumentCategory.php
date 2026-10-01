<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentCategory extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rental_monthly_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(InstrumentAsset::class);
    }
}
