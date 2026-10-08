<?php

namespace App\Models;

use App\Enums\RentalAcceptanceStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'rental_id', 'recipient_name', 'recipient_email', 'token_hash', 'status', 'consent_text',
    'expires_at', 'sent_at', 'accepted_at', 'refused_at', 'cancelled_at', 'accepted_name',
    'accepted_ip_address', 'accepted_user_agent',
])]
class RentalAcceptanceRequest extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => RentalAcceptanceStatus::class,
            'expires_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'refused_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(RentalDocument::class, 'rental_acceptance_request_documents')
            ->withPivot(['document_sha256'])
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(RentalAcceptanceEvent::class, 'request_id');
    }
}
