<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'email_mailbox_id', 'status', 'started_at', 'finished_at', 'folders_count',
    'imported_count', 'skipped_count', 'error_message',
])]
class EmailSyncRun extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $run): void {
            if (! EmailMailbox::query()->whereKey($run->email_mailbox_id)->exists()) {
                throw new LogicException('The email sync run does not belong to the active organization.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(EmailMailbox::class, 'email_mailbox_id');
    }
}
