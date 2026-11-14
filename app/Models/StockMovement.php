<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_change' => 'decimal:2',
            'quantity_after' => 'decimal:2',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function workshopOrder(): BelongsTo
    {
        return $this->belongsTo(WorkshopOrder::class);
    }
}
