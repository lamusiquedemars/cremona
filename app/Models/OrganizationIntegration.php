<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'name', 'credentials', 'key_id', 'token_hash', 'status', 'revoked_at'])]
#[Hidden(['credentials', 'token_hash'])]
class OrganizationIntegration extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
