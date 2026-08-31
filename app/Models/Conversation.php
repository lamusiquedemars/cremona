<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\MessageChannel;
use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesOrganizationAssignee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'person_id', 'company_id', 'incoming_request_id', 'assigned_user_id',
    'initial_channel', 'subject', 'normalized_subject', 'status',
    'first_message_at', 'last_message_at', 'last_inbound_at', 'last_outbound_at',
    'closed_at', 'archived_at',
])]
class Conversation extends Model
{
    use BelongsToOrganization, ValidatesOrganizationAssignee;

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            $conversation->public_id ??= (string) Str::ulid();
        });

        static::saving(function (self $conversation): void {
            $conversation->subject = self::clean($conversation->subject);
            $conversation->normalized_subject = self::normalizeSubject($conversation->subject);

            foreach ([
                Person::class => $conversation->person_id,
                Company::class => $conversation->company_id,
                IncomingRequest::class => $conversation->incoming_request_id,
            ] as $model => $id) {
                if ($id !== null && ! $model::query()->whereKey($id)->exists()) {
                    throw new LogicException('A conversation relation does not belong to the active organization.');
                }
            }
        });

        static::deleting(fn () => throw new LogicException(
            'Conversations must be archived or erased through the retention process.',
        ));
    }

    protected function casts(): array
    {
        return [
            'initial_channel' => MessageChannel::class,
            'status' => ConversationStatus::class,
            'first_message_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'last_inbound_at' => 'immutable_datetime',
            'last_outbound_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany('authored_at');
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(ConversationUserState::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function normalizeSubject(?string $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        $subject = preg_replace('/^(?:(?:re|fw|fwd|aw|tr)\s*:\s*)+/iu', '', $subject) ?? $subject;
        $subject = preg_replace('/\s+/u', ' ', trim($subject)) ?? trim($subject);

        return $subject !== '' ? mb_strtolower($subject) : null;
    }
}
