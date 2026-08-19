<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'conversation_message_id', 'conversation_id', 'confidence', 'reason', 'status',
    'resolved_by_user_id', 'resolved_at',
])]
class MessageThreadCandidate extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $candidate): void {
            if (! ConversationMessage::query()->whereKey($candidate->conversation_message_id)->exists()
                || ! Conversation::query()->whereKey($candidate->conversation_id)->exists()) {
                throw new LogicException('The threading candidate does not belong to the active organization.');
            }

            $candidate->status ??= 'proposed';

            if ($candidate->confidence < 0 || $candidate->confidence > 1
                || ! in_array($candidate->status, ['proposed', 'selected', 'rejected'], true)) {
                throw new LogicException('The threading candidate is invalid.');
            }
        });
    }

    protected function casts(): array
    {
        return ['confidence' => 'decimal:4', 'resolved_at' => 'immutable_datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
