<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_integration_id', 'address', 'display_name', 'status',
    'inbox_folder', 'sent_folder', 'last_synced_at', 'last_error_at', 'last_error',
])]
class EmailMailbox extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'immutable_datetime',
            'last_error_at' => 'immutable_datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(OrganizationIntegration::class, 'organization_integration_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(EmailFolder::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(EmailSyncRun::class);
    }
}
