<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'campaign_id',
    'metric_date',
    'spend',
    'impressions',
    'clicks',
    'platform_conversions',
    'currency',
    'source',
    'metadata',
])]
class CampaignDailyMetric extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'metric_date' => 'immutable_date',
            'spend' => 'decimal:2',
            'platform_conversions' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
