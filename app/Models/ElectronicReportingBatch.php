<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['remote_id', 'kind', 'role_code', 'start_period', 'end_period', 'events', 'synchronized_at'])]
class ElectronicReportingBatch extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'start_period' => 'immutable_date',
            'end_period' => 'immutable_date',
            'events' => 'array',
            'synchronized_at' => 'immutable_datetime',
        ];
    }
}
