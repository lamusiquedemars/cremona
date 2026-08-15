<?php

namespace App\Models;

use App\Enums\AppointmentModality;
use App\Enums\AppointmentStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesOrganizationAssignee;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'assigned_user_id',
    'person_id',
    'company_id',
    'incoming_request_id',
    'title',
    'status',
    'starts_at',
    'ends_at',
    'timezone',
    'modality',
    'location',
    'meeting_url',
    'provider',
    'external_reference',
    'description',
    'cancelled_at',
    'completed_at',
])]
class Appointment extends Model
{
    use BelongsToOrganization, ValidatesOrganizationAssignee;

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $appointment->public_id ??= (string) Str::ulid();
        });

        static::saving(function (Appointment $appointment): void {
            $appointment->title = trim($appointment->title);
            $organization = app(OrganizationContext::class)->require();
            $appointment->timezone = trim((string) $appointment->timezone)
                ?: (string) ($organization->settings['timezone'] ?? config('app.timezone', 'UTC'));

            if ($appointment->title === '') {
                throw new LogicException('An appointment requires a title.');
            }

            if ($appointment->ends_at <= $appointment->starts_at) {
                throw new LogicException('An appointment must end after it starts.');
            }

            if (! in_array($appointment->timezone, timezone_identifiers_list(), true)) {
                throw new LogicException('An appointment requires a valid timezone.');
            }

            $appointment->cancelled_at = $appointment->status === AppointmentStatus::Cancelled
                ? ($appointment->cancelled_at ?? now())
                : null;
            $appointment->completed_at = $appointment->status === AppointmentStatus::Completed
                ? ($appointment->completed_at ?? now())
                : null;

            foreach ([
                Person::class => $appointment->person_id,
                Company::class => $appointment->company_id,
                IncomingRequest::class => $appointment->incoming_request_id,
            ] as $model => $id) {
                if ($id !== null && ! $model::query()->whereKey($id)->exists()) {
                    throw new LogicException('An appointment relation does not belong to the active organization.');
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'modality' => AppointmentModality::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
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

    public function incomingRequest(): BelongsTo
    {
        return $this->belongsTo(IncomingRequest::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
