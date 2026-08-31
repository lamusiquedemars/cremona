<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['email_folder_id', 'conversation_message_id', 'uid_validity', 'uid', 'synchronized_at'])]
class EmailMessageCopy extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $copy): void {
            if (! EmailFolder::query()->whereKey($copy->email_folder_id)->exists()
                || ! ConversationMessage::query()->whereKey($copy->conversation_message_id)->exists()) {
                throw new LogicException('The email copy does not belong to the active organization.');
            }
        });
    }

    protected function casts(): array
    {
        return ['synchronized_at' => 'immutable_datetime'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(EmailFolder::class, 'email_folder_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
