<?php

namespace App\Models;

use App\Enums\IncomingRequestStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesIncomingRequestOwnership;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'incoming_request_id',
    'actor_user_id',
    'related_user_id',
    'related_person_id',
    'related_company_id',
    'event',
    'from_status',
    'to_status',
    'body',
    'recorded_at',
])]
class IncomingRequestActivity extends Model
{
    use BelongsToOrganization, ValidatesIncomingRequestOwnership;

    protected static function booted(): void
    {
        static::saving(function (IncomingRequestActivity $activity): void {
            $organization = app(OrganizationContext::class)->require();

            foreach ([$activity->actor_user_id, $activity->related_user_id] as $userId) {
                if ($userId === null) {
                    continue;
                }

                $user = User::query()->find($userId);

                if ($user === null || (! $user->is_platform_admin
                    && ! $user->organizations()->whereKey($organization)->exists())) {
                    throw new LogicException('The activity user is not a member of the active organization.');
                }
            }

            if ($activity->related_person_id !== null
                && ! Person::query()->whereKey($activity->related_person_id)->exists()) {
                throw new LogicException('The activity person does not belong to the active organization.');
            }

            if ($activity->related_company_id !== null
                && ! Company::query()->whereKey($activity->related_company_id)->exists()) {
                throw new LogicException('The activity company does not belong to the active organization.');
            }
        });

        static::updating(fn () => throw new LogicException('CRM activity entries are immutable.'));
        static::deleting(fn () => throw new LogicException('CRM activity entries are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'from_status' => IncomingRequestStatus::class,
            'to_status' => IncomingRequestStatus::class,
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function incomingRequest(): BelongsTo
    {
        return $this->belongsTo(IncomingRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }

    public function relatedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'related_company_id');
    }
}
