<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageThreadingStatus;
use App\Enums\MessageTransportStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'conversation_id', 'author_user_id', 'direction', 'channel', 'subject',
    'body_text', 'body_html_sanitized', 'message_id', 'canonical_message_id', 'message_id_hash',
    'in_reply_to', 'canonical_in_reply_to', 'in_reply_to_hash', 'transport_status', 'threading_status',
    'idempotency_key', 'payload_fingerprint', 'authored_at', 'queued_at',
    'accepted_at', 'received_at', 'failed_at', 'failure_code', 'failure_message',
])]
class ConversationMessage extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->public_id ??= (string) Str::ulid();
        });

        static::saving(function (self $message): void {
            $message->body_text = trim($message->body_text);

            if ($message->body_text === '') {
                throw new LogicException('A conversation message requires text content.');
            }

            if ($message->conversation_id !== null
                && ! Conversation::query()->whereKey($message->conversation_id)->exists()) {
                throw new LogicException('The conversation does not belong to the active organization.');
            }

            if ($message->author_user_id !== null) {
                $organization = app(OrganizationContext::class)->require();
                $author = User::query()->find($message->author_user_id);

                if ($author === null || (! $author->is_platform_admin
                    && ! $author->organizations()->whereKey($organization)->exists())) {
                    throw new LogicException('The message author is not a member of the active organization.');
                }
            }

            $message->message_id = self::clean($message->message_id);
            $message->canonical_message_id = self::canonicalHeaderId($message->message_id);
            $message->message_id_hash = self::headerHash($message->canonical_message_id);
            $message->in_reply_to = self::clean($message->in_reply_to);
            $message->canonical_in_reply_to = self::canonicalHeaderId($message->in_reply_to);
            $message->in_reply_to_hash = self::headerHash($message->canonical_in_reply_to);
            $message->failure_code = self::clean($message->failure_code);
            $message->failure_message = self::clean($message->failure_message);
        });

        static::updating(function (self $message): void {
            if ($message->isDirty(['body_text', 'body_html_sanitized'])
                && $message->transport_status !== MessageTransportStatus::Draft) {
                throw new LogicException('Only draft message content can be edited.');
            }
        });

        static::deleting(fn () => throw new LogicException(
            'Messages must be erased through the retention process.',
        ));
    }

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'channel' => MessageChannel::class,
            'transport_status' => MessageTransportStatus::class,
            'threading_status' => MessageThreadingStatus::class,
            'authored_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MessageParticipant::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(MessageReference::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function threadCandidates(): HasMany
    {
        return $this->hasMany(MessageThreadCandidate::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function canonicalHeaderId(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[<>\s]/', '', $value) ?? $value;

        return $value !== '' ? mb_strtolower($value) : null;
    }

    public static function headerHash(?string $value): ?string
    {
        return $value === null ? null : hash('sha256', $value);
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
