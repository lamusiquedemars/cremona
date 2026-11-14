<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider', 'remote_id', 'reference', 'supplier_name', 'issued_on', 'status', 'payload', 'original_private_document_id', 'synchronized_at'])]
class IncomingInvoice extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'issued_on' => 'immutable_date',
            'payload' => 'array',
            'synchronized_at' => 'immutable_datetime',
        ];
    }

    public function originalPrivateDocument(): BelongsTo
    {
        return $this->belongsTo(PrivateDocument::class, 'original_private_document_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IncomingInvoiceEvent::class)->orderBy('occurred_at')->orderBy('id');
    }
}
