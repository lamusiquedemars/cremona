<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['module', 'enabled', 'configuration'])]
class OrganizationModule extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'configuration' => 'array',
        ];
    }
}
