<?php

namespace App\Models;

use App\Enums\OrganizationPermission;
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

    public function grants(OrganizationPermission $permission): bool
    {
        $overrides = $this->permissions ?? [];

        if (array_key_exists($permission->value, $overrides)) {
            return (bool) $overrides[$permission->value];
        }

        return $this->role->grants($permission);
    }

    public function canAccessModule(string $module): bool
    {
        $modules = data_get($this->permissions, 'modules');

        // L'absence de liste conserve le profil existant. Une liste, même
        // vide, est un choix explicite de modules autorisés.
        return ! is_array($modules) || in_array($module, $modules, true);
    }

    public function canManageModule(string $module): bool
    {
        return $this->canAccessModule($module)
            && $this->role->canManageOperationalData();
    }
}
