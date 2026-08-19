<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesOrganizationAssignee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'assigned_user_id',
    'name',
    'legal_name',
    'website',
    'industry',
    'source',
    'status',
    'last_activity_at',
    'archived_at',
])]
class Company extends Model
{
    use BelongsToOrganization, ValidatesOrganizationAssignee;

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class)
            ->withPivot(['organization_id', 'job_title', 'is_primary', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function personRelationships(): HasMany
    {
        return $this->hasMany(CompanyPerson::class);
    }

    public function contactMethods(): MorphMany
    {
        return $this->morphMany(ContactMethod::class, 'contactable');
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

    public function notes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'notable');
    }
}
