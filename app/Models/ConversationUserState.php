<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['conversation_id', 'user_id', 'last_read_message_id', 'last_read_at'])]
class ConversationUserState extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $state): void {
            if (! Conversation::query()->whereKey($state->conversation_id)->exists()) {
                throw new LogicException('The conversation state does not belong to the active organization.');
            }

            $organization = app(OrganizationContext::class)->require();
            $user = User::query()->find($state->user_id);

            if ($user === null || (! $user->is_platform_admin
                && ! $user->organizations()->whereKey($organization)->exists())) {
                throw new LogicException('The conversation state user is not a member of the active organization.');
            }

            if ($state->last_read_message_id !== null
                && ! ConversationMessage::query()
                    ->whereKey($state->last_read_message_id)
                    ->where('conversation_id', $state->conversation_id)
                    ->exists()) {
                throw new LogicException('The read message does not belong to this conversation.');
            }
        });
    }

    protected function casts(): array
    {
        return ['last_read_at' => 'immutable_datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'last_read_message_id');
    }
}
