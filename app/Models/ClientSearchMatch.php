<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ClientSearchMatch extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $match): void {
            if ($match->client_search_id === null && $match->person_id === null) {
                throw new LogicException('Une correspondance doit concerner une recherche ou un client.');
            }

            if ($match->client_search_id !== null && $match->person_id !== null) {
                throw new LogicException('Une correspondance ne peut pas associer à la fois une recherche et un client direct.');
            }

            if ($match->client_search_id !== null && ! ClientSearch::query()->whereKey($match->client_search_id)->exists()) {
                throw new LogicException('La recherche client ne relève pas de l’organisation active.');
            }

            if ($match->person_id !== null && ! Person::query()->whereKey($match->person_id)->exists()) {
                throw new LogicException('Le client ne relève pas de l’organisation active.');
            }

            if (! Availability::query()->whereKey($match->availability_id)->exists()) {
                throw new LogicException('La disponibilité ne relève pas de l’organisation active.');
            }
        });
    }

    protected function casts(): array
    {
        return ['prepared_at' => 'immutable_datetime', 'contacted_at' => 'immutable_datetime', 'dismissed_at' => 'immutable_datetime'];
    }

    public function search(): BelongsTo { return $this->belongsTo(ClientSearch::class, 'client_search_id'); }

    public function person(): BelongsTo { return $this->belongsTo(Person::class); }

    public function availability(): BelongsTo { return $this->belongsTo(Availability::class); }

    public function preparedBy(): BelongsTo { return $this->belongsTo(User::class, 'prepared_user_id'); }
}
