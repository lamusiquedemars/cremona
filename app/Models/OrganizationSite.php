<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'base_url', 'status', 'integration_key'])]
class OrganizationSite extends Model
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
