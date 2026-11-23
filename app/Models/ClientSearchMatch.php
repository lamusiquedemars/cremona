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
            if (! ClientSearch::query()->whereKey($match->client_search_id)->exists()) {
                throw new LogicException('La recherche client ne relève pas de l’organisation active.');
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

    public function availability(): BelongsTo { return $this->belongsTo(Availability::class); }

    public function preparedBy(): BelongsTo { return $this->belongsTo(User::class, 'prepared_user_id'); }
}
