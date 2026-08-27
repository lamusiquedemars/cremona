<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationPermission;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'is_platform_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    public const SUPER_ADMIN_EMAILS = ['ivo@maracujadigital.fr'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function getIsPlatformAdminAttribute(mixed $value): bool
    {
        return (bool) $value || in_array(strtolower($this->email), self::SUPER_ADMIN_EMAILS, true);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationMembership::class)
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function assignedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'assigned_user_id');
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    public function authoredConversationMessages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'author_user_id');
    }

    public function hasOrganizationPermission(
        OrganizationPermission $permission,
        Organization $organization,
    ): bool {
        if ($this->is_platform_admin) {
            return true;
        }

        $membership = $this->memberships()
            ->where('organization_id', $organization->getKey())
            ->first();

        return $membership?->grants($permission) ?? false;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'platform') {
            return $this->is_platform_admin;
        }

        return $this->is_platform_admin || $this->organizations()->where('status', 'active')->exists();
    }

    public function getTenants(Panel $panel): array|Collection
    {
        if ($this->is_platform_admin) {
            return Organization::query()->where('status', 'active')->orderBy('name')->get();
        }

        return $this->organizations()->where('status', 'active')->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Organization || ! $tenant->is_active) {
            return false;
        }

        return $this->is_platform_admin || $this->organizations()->whereKey($tenant)->exists();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        if ($this->is_platform_admin) {
            return null;
        }

        return $this->getTenants($panel)->first();
    }
}
