<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rental_id', 'returned_on', 'accessories_state', 'condition_notes', 'charge_amount', 'charge_note', 'recorded_by_user_id', 'recorded_at'])]
class RentalReturn extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['returned_on' => 'immutable_date', 'recorded_at' => 'immutable_datetime', 'charge_amount' => 'decimal:2'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
