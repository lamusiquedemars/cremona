<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationMembership extends Pivot
{
    protected $table = 'organization_user';

    public $incrementing = true;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'permissions' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
