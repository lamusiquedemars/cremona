<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'email_mailbox_id', 'remote_name', 'role', 'uid_validity', 'last_uid',
    'sync_status', 'last_synced_at', 'last_error_at', 'last_error',
])]
class EmailFolder extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $folder): void {
            if (! EmailMailbox::query()->whereKey($folder->email_mailbox_id)->exists()) {
                throw new LogicException('The email folder does not belong to the active organization.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'immutable_datetime',
            'last_error_at' => 'immutable_datetime',
        ];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(EmailMailbox::class, 'email_mailbox_id');
    }

    public function messageCopies(): HasMany
    {
        return $this->hasMany(EmailMessageCopy::class);
    }
}
