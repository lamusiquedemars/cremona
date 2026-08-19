<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'conversation_message_id', 'disk', 'path', 'original_name', 'declared_mime_type',
    'detected_mime_type', 'size', 'sha256', 'content_id', 'disposition', 'scan_status', 'scanned_at',
])]
class MessageAttachment extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $attachment): void {
            if (! ConversationMessage::query()->whereKey($attachment->conversation_message_id)->exists()) {
                throw new LogicException('The message does not belong to the active organization.');
            }

            $attachment->disposition ??= 'attachment';
            $attachment->scan_status ??= 'pending';

            if ($attachment->disk === 'public'
                || ! in_array($attachment->disposition, ['attachment', 'inline'], true)
                || ! preg_match('/^[a-f0-9]{64}$/', $attachment->sha256)
                || $attachment->size < 0) {
                throw new LogicException('The attachment metadata is invalid.');
            }
        });
    }

    protected function casts(): array
    {
        return ['scanned_at' => 'immutable_datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
