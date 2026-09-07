<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;

#[Fillable(['private_document_id', 'linkable_type', 'linkable_id'])]
class PrivateDocumentLink extends Model
{
    use BelongsToOrganization;

    public const LINKABLE_MODELS = [
        'person' => Person::class,
        'company' => Company::class,
        'incoming_request' => IncomingRequest::class,
        'conversation' => Conversation::class,
        'crm_task' => CrmTask::class,
        'appointment' => Appointment::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            $document = PrivateDocument::query()->whereKey($link->private_document_id)->first();
            $model = Relation::getMorphedModel($link->linkable_type);

            if ($document === null
                || ! in_array($model, self::LINKABLE_MODELS, true)
                || ! $model::query()->whereKey($link->linkable_id)->exists()) {
                throw new LogicException('Le rattachement du document privé ne relève pas de l’organisation active.');
            }

            if ((int) $document->organization_id !== (int) $link->organization_id) {
                throw new LogicException('Le rattachement du document doit appartenir à la même organisation.');
            }
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PrivateDocument::class, 'private_document_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
