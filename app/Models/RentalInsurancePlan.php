<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalInsurancePlan extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['eligible_sizes' => 'array', 'monthly_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function rentalTier(): BelongsTo
    {
        return $this->belongsTo(RentalTier::class);
    }
}
