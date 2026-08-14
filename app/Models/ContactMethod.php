<?php

namespace App\Models;

use App\Enums\ContactMethodType;
use App\Support\ContactValueNormalizer;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable([
    'type',
    'label',
    'value',
    'is_primary',
    'is_verified',
    'deliverability_status',
    'verified_at',
    'last_used_at',
])]
class ContactMethod extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (ContactMethod $method): void {
            $type = $method->type instanceof ContactMethodType
                ? $method->type
                : ContactMethodType::from($method->type);
            $method->value = trim($method->value);
            $method->normalized_value = app(ContactValueNormalizer::class)->normalize($type, $method->value);

            if ($method->value === '' || $method->normalized_value === '') {
                throw new LogicException('A contact method requires a value.');
            }

            if (! $method->contactable()->exists()) {
                throw new LogicException('The contact method owner does not belong to the active organization.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ContactMethodType::class,
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
