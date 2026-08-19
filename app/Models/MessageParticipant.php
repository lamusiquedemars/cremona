<?php

namespace App\Models;

use App\Enums\MessageParticipantRole;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['conversation_message_id', 'contact_method_id', 'role', 'name', 'address', 'position'])]
class MessageParticipant extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $participant): void {
            if (! ConversationMessage::query()->whereKey($participant->conversation_message_id)->exists()) {
                throw new LogicException('The message does not belong to the active organization.');
            }

            if ($participant->contact_method_id !== null
                && ! ContactMethod::query()->whereKey($participant->contact_method_id)->exists()) {
                throw new LogicException('The contact method does not belong to the active organization.');
            }

            $participant->name = self::clean($participant->name);
            $participant->address = trim($participant->address);
            $participant->normalized_address = mb_strtolower($participant->address);

            if ($participant->address === '' || ! filter_var($participant->address, FILTER_VALIDATE_EMAIL)) {
                throw new LogicException('A message participant requires a valid email address.');
            }
        });
    }

    protected function casts(): array
    {
        return ['role' => MessageParticipantRole::class];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function contactMethod(): BelongsTo
    {
        return $this->belongsTo(ContactMethod::class);
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
