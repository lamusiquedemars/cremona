<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopOrderService extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_amount' => 'decimal:2', 'include_in_quote' => 'boolean'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ServiceDefinition::class, 'service_definition_id');
    }
}
