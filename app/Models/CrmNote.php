<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable([
    'author_user_id',
    'body',
])]
class CrmNote extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (CrmNote $note): void {
            $organization = app(OrganizationContext::class)->require();
            $note->body = trim($note->body);

            if ($note->body === '') {
                throw new LogicException('A CRM note cannot be empty.');
            }

            $notable = $note->notable;

            if ((! $notable instanceof Person && ! $notable instanceof Company)
                || (int) $notable->organization_id !== $organization->getKey()) {
                throw new LogicException('The note subject does not belong to the active organization.');
            }

            $author = User::query()->find($note->author_user_id);

            if ($author === null || (! $author->is_platform_admin
                && ! $author->organizations()->whereKey($organization)->exists())) {
                throw new LogicException('The note author is not a member of the active organization.');
            }
        });

        static::created(function (CrmNote $note): void {
            $note->notable()->update(['last_activity_at' => $note->created_at]);
        });

        static::updating(fn () => throw new LogicException('CRM notes are immutable.'));
        static::deleting(fn () => throw new LogicException('CRM notes are immutable.'));
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
