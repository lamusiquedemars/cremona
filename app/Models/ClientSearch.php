<?php

namespace App\Models;

use App\Services\ClientSearchMatcher;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['person_id', 'object_type', 'size', 'maximum_budget', 'notes', 'status', 'closed_at'])]
class ClientSearch extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $search): void {
            $search->status ??= 'open';
        });
        static::saving(function (self $search): void {
            if ($search->person_id !== null && ! Person::query()->whereKey($search->person_id)->exists()) {
                throw new LogicException('Le client de cette recherche ne relève pas de l’organisation active.');
            }
            $search->closed_at = $search->status === 'closed' ? ($search->closed_at ?? now()) : null;
        });
        static::saved(fn (self $search) => app(ClientSearchMatcher::class)->syncSearch($search));
    }

    protected function casts(): array
    {
        return ['maximum_budget' => 'decimal:2', 'closed_at' => 'immutable_datetime'];
    }

    public function person(): BelongsTo { return $this->belongsTo(Person::class); }

    public function matches(): HasMany { return $this->hasMany(ClientSearchMatch::class); }
}
