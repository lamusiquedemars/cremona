<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'base_url', 'status', 'integration_key', 'site_portal_enabled', 'site_portal_client_id', 'site_portal_secret', 'site_portal_items', 'site_portal_logout_redirect_uri'])]
class OrganizationSite extends Model
{
    protected function casts(): array
    {
        return [
            'site_portal_enabled' => 'boolean',
            'site_portal_secret' => 'encrypted',
            'site_portal_items' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
