<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'kind', 'description', 'default_quantity', 'default_unit_amount', 'default_tax_rate', 'is_active'])]
class QuoteLineTemplate extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'default_quantity' => 'decimal:2',
            'default_unit_amount' => 'decimal:2',
            'default_tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }
}
