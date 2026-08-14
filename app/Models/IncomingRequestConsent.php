<?php

namespace App\Models;

use App\Enums\ConsentStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesIncomingRequestOwnership;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incoming_request_id',
    'purpose',
    'channel',
    'status',
    'statement_snapshot',
    'statement_version',
    'source',
    'granted_at',
    'withdrawn_at',
])]
class IncomingRequestConsent extends Model
{
    use BelongsToOrganization, ValidatesIncomingRequestOwnership;

    protected function casts(): array
    {
        return [
            'status' => ConsentStatus::class,
            'granted_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    public function incomingRequest(): BelongsTo
    {
        return $this->belongsTo(IncomingRequest::class);
    }
}
