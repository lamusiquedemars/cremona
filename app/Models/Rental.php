<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Rental extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $rental): void {
            $rental->public_id ??= (string) Str::ulid();
            $rental->reference ??= 'LOC-'.str($rental->public_id)->substr(0, 8);
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
            'deposit_amount' => 'decimal:2',
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

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
