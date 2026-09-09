<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_on_hand' => 'decimal:2', 'reorder_level' => 'decimal:2', 'suggested_unit_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
