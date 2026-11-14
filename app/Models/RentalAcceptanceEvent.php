<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['request_id', 'event', 'metadata', 'ip_address', 'user_agent', 'created_at'])]
class RentalAcceptanceEvent extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Les preuves d’acceptation sont immuables.'));
        static::deleting(fn () => throw new LogicException('Les preuves d’acceptation ne sont pas supprimées directement.'));
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RentalAcceptanceRequest::class, 'request_id');
    }
}
