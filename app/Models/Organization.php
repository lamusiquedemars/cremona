<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'vertical_pack', 'status', 'settings'])]
class Organization extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationMembership::class)
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(OrganizationModule::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === 'active');
    }
}
