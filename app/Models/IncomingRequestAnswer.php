<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesIncomingRequestOwnership;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['incoming_request_id', 'field_key', 'label_snapshot', 'value_type', 'value', 'position'])]
class IncomingRequestAnswer extends Model
{
    use BelongsToOrganization, ValidatesIncomingRequestOwnership;

    public function incomingRequest(): BelongsTo
    {
        return $this->belongsTo(IncomingRequest::class);
    }
}
