<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'channel',
    'tracking_key',
    'external_reference',
    'site_reference',
    'status',
    'starts_on',
    'ends_on',
    'planned_budget',
    'currency',
    'notes',
    'configuration',
])]
class Campaign extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'planned_budget' => 'decimal:2',
            'configuration' => 'array',
        ];
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(CampaignDailyMetric::class);
    }

    public function attributedIncomingRequests(): HasMany
    {
        return $this->hasMany(IncomingRequest::class, 'attribution_campaign', 'tracking_key');
    }
}
