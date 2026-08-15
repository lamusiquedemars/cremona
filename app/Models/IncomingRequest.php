<?php

namespace App\Models;

use App\Enums\IncomingRequestOutcome;
use App\Enums\IncomingRequestStatus;
use App\Enums\IncomingRequestUrgency;
use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesOrganizationAssignee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'idempotency_key',
    'payload_fingerprint',
    'assigned_user_id',
    'person_id',
    'company_id',
    'source_channel',
    'source',
    'source_site_reference',
    'source_form_reference',
    'attribution_source',
    'attribution_medium',
    'attribution_campaign',
    'name_snapshot',
    'email_snapshot',
    'phone_snapshot',
    'subject',
    'message',
    'category',
    'urgency',
    'important_date',
    'status',
    'outcome',
    'received_at',
    'read_at',
    'started_at',
    'qualified_at',
    'closed_at',
    'archived_at',
])]
class IncomingRequest extends Model
{
    use BelongsToOrganization, ValidatesOrganizationAssignee;

    protected static function booted(): void
    {
        static::creating(function (IncomingRequest $request): void {
            $request->public_id ??= (string) Str::ulid();
        });

        static::saving(function (IncomingRequest $request): void {
            if ($request->person_id !== null && ! Person::query()->whereKey($request->person_id)->exists()) {
                throw new LogicException('The person does not belong to the active organization.');
            }

            if ($request->company_id !== null && ! Company::query()->whereKey($request->company_id)->exists()) {
                throw new LogicException('The company does not belong to the active organization.');
            }
        });

        static::deleting(fn () => throw new LogicException(
            'Incoming requests must be archived or erased through a dedicated retention process.',
        ));
    }

    protected function casts(): array
    {
        return [
            'status' => IncomingRequestStatus::class,
            'outcome' => IncomingRequestOutcome::class,
            'urgency' => IncomingRequestUrgency::class,
            'important_date' => 'immutable_date',
            'received_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'qualified_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(IncomingRequestAnswer::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(IncomingRequestConsent::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(IncomingRequestActivity::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
