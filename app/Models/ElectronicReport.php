<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['invoice_id', 'source_type', 'source_id', 'kind', 'scope', 'status', 'occurred_on', 'amount', 'currency', 'payload', 'remote_id', 'submitted_at', 'acknowledged_at', 'last_error'])]
class ElectronicReport extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'occurred_on' => 'immutable_date',
            'amount' => 'decimal:2',
            'payload' => 'array',
            'submitted_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
