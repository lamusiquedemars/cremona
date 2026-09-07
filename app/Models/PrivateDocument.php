<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'public_id', 'uploaded_by_user_id', 'version_of_id', 'version_number', 'title', 'category',
    'disk', 'path', 'original_name', 'declared_mime_type', 'detected_mime_type', 'size', 'sha256',
])]
class PrivateDocument extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->public_id ??= (string) Str::ulid();
            $document->version_number ??= 1;
        });

        static::saving(function (self $document): void {
            $document->title = self::clean($document->title);
            $document->category = self::clean($document->category);

            if ($document->exists && $document->isDirty([
                'disk', 'path', 'original_name', 'declared_mime_type', 'detected_mime_type', 'size', 'sha256',
            ])) {
                throw new LogicException('Le contenu d’un document privé ne peut pas être remplacé : créez une nouvelle version.');
            }

            if ($document->disk !== 'local'
                || ! preg_match('/^[a-f0-9]{64}$/', (string) $document->sha256)
                || $document->size < 0
                || blank($document->path)
                || blank($document->original_name)) {
                throw new LogicException('Les métadonnées du document privé sont invalides.');
            }

            if ($document->version_of_id !== null) {
                $previous = self::query()->whereKey($document->version_of_id)->first();

                if ($previous === null || $previous->organization_id !== $document->organization_id) {
                    throw new LogicException('La version précédente ne relève pas de l’organisation active.');
                }

                if ($document->version_number <= $previous->version_number) {
                    throw new LogicException('Le numéro de version doit être supérieur à la version précédente.');
                }
            }
        });

        static::deleting(fn () => throw new LogicException(
            'Les documents privés ne sont pas supprimés directement.',
        ));
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'version_of_id');
    }

    public function subsequentVersions(): HasMany
    {
        return $this->hasMany(self::class, 'version_of_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(PrivateDocumentLink::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
