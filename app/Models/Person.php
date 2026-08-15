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
use LogicException;

#[Fillable([
    'assigned_user_id',
    'first_name',
    'last_name',
    'display_name',
    'locale',
    'country_code',
    'source',
    'status',
    'last_activity_at',
    'archived_at',
])]
class Person extends Model
{
    use BelongsToOrganization, ValidatesOrganizationAssignee;

    protected static function booted(): void
    {
        static::saving(function (Person $person): void {
            $person->first_name = self::clean($person->first_name);
            $person->last_name = self::clean($person->last_name);
            $person->display_name = self::clean($person->display_name)
                ?? self::clean(trim("{$person->first_name} {$person->last_name}"))
                ?? throw new LogicException('A person requires a display name.');
            $person->country_code = self::clean($person->country_code) !== null
                ? mb_strtoupper((string) $person->country_code)
                : null;
        });
    }

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

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['organization_id', 'job_title', 'is_primary', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function companyRelationships(): HasMany
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

    public function notes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'notable');
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
