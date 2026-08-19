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

    public function auditLogs(): HasMany
    {
        return $this->hasMany(OrganizationAuditLog::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(OrganizationIntegration::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function incomingRequests(): HasMany
    {
        return $this->hasMany(IncomingRequest::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function conversationMessages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
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
