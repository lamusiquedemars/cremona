<?php

namespace App\Models;

use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'rental_id', 'private_document_id', 'version_of_id', 'version_number', 'type', 'status',
    'template_version', 'snapshot', 'content_sha256', 'generated_at', 'created_by_user_id',
])]
class RentalDocument extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->public_id ??= (string) Str::ulid();
            $document->version_number ??= 1;
        });

        static::creating(function (self $document): void {
            if ($document->status === RentalDocumentStatus::Generated) {
                if ($document->private_document_id === null
                    || blank($document->template_version)
                    || ! is_array($document->snapshot)
                    || $document->snapshot === []
                    || ! preg_match('/^[a-f0-9]{64}$/', (string) $document->content_sha256)
                    || $document->generated_at === null) {
                    throw new LogicException('Un document généré doit posséder son PDF, son modèle, son instantané, son hash et sa date de génération.');
                }
            }

            $rental = Rental::query()->whereKey($document->rental_id)->first();
            if ($rental === null || (int) $rental->organization_id !== (int) $document->organization_id) {
                throw new LogicException('Le document de location doit appartenir à une location de l’organisation active.');
            }

            if ($document->private_document_id !== null) {
                $privateDocument = PrivateDocument::query()->whereKey($document->private_document_id)->first();
                if ($privateDocument === null
                    || (int) $privateDocument->organization_id !== (int) $document->organization_id
                    || $privateDocument->sha256 !== $document->content_sha256) {
                    throw new LogicException('Le PDF du document de location est invalide ou relève d’une autre organisation.');
                }
            }

            if ($document->version_of_id !== null) {
                $previous = self::query()->whereKey($document->version_of_id)->first();
                if ($previous === null
                    || $previous->organization_id !== $document->organization_id
                    || $previous->rental_id !== $document->rental_id
                    || $previous->type !== $document->type
                    || $document->version_number <= $previous->version_number) {
                    throw new LogicException('La version précédente du document de location est invalide.');
                }
            }
        });

        static::updating(function (self $document): void {
            if ($document->isDirty([
                'rental_id', 'private_document_id', 'version_of_id', 'version_number', 'type',
                'template_version', 'snapshot', 'content_sha256', 'generated_at',
            ])) {
                throw new LogicException('Le contenu d’un document de location généré ne peut pas être modifié. Créez une nouvelle version.');
            }
        });

        static::deleting(fn () => throw new LogicException('Les documents de location ne sont pas supprimés directement.'));
    }

    protected function casts(): array
    {
        return [
            'type' => RentalDocumentType::class,
            'status' => RentalDocumentStatus::class,
            'snapshot' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function privateDocument(): BelongsTo
    {
        return $this->belongsTo(PrivateDocument::class);
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'version_of_id');
    }

    public function subsequentVersions(): HasMany
    {
        return $this->hasMany(self::class, 'version_of_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
