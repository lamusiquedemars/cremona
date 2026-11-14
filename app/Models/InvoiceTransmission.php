<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider', 'direction', 'status', 'external_id', 'payload_sha256', 'electronic_private_document_id', 'metadata', 'last_error', 'submitted_at', 'completed_at'])]
class InvoiceTransmission extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'submitted_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function electronicPrivateDocument(): BelongsTo
    {
        return $this->belongsTo(PrivateDocument::class, 'electronic_private_document_id');
    }
}
