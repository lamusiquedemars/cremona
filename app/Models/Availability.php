<?php

namespace App\Models;

use App\Services\ClientSearchMatcher;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['instrument_asset_id', 'title', 'object_type', 'size', 'amount', 'source', 'source_detail', 'notes', 'status', 'available_at', 'closed_at'])]
class Availability extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $availability): void {
            if ($availability->instrument_asset_id !== null && ! InstrumentAsset::query()->whereKey($availability->instrument_asset_id)->exists()) {
                throw new LogicException('L’instrument de cette disponibilité ne relève pas de l’organisation active.');
            }
            if ($availability->status === 'available') {
                $availability->available_at ??= now();
                $availability->closed_at = null;
            } elseif ($availability->closed_at === null) {
                $availability->closed_at = now();
            }
        });
        static::saved(fn (self $availability) => app(ClientSearchMatcher::class)->syncAvailability($availability));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'available_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    public function instrumentAsset(): BelongsTo { return $this->belongsTo(InstrumentAsset::class); }

    public function matches(): HasMany { return $this->hasMany(ClientSearchMatch::class); }
}
