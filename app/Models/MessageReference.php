<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['conversation_message_id', 'reference', 'position'])]
class MessageReference extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $reference): void {
            if (! ConversationMessage::query()->whereKey($reference->conversation_message_id)->exists()) {
                throw new LogicException('The message does not belong to the active organization.');
            }

            $reference->reference = trim($reference->reference);
            $reference->canonical_reference = ConversationMessage::canonicalHeaderId($reference->reference)
                ?? throw new LogicException('A message reference requires an identifier.');
            $reference->reference_hash = ConversationMessage::headerHash($reference->canonical_reference);
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
