<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'description', 'suggested_unit_amount', 'tax_rate', 'is_active'])]
class ServiceDefinition extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['suggested_unit_amount' => 'decimal:2', 'tax_rate' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
