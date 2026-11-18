<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSearchMatch extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['prepared_at' => 'immutable_datetime', 'contacted_at' => 'immutable_datetime', 'dismissed_at' => 'immutable_datetime'];
    }

    public function search(): BelongsTo { return $this->belongsTo(ClientSearch::class, 'client_search_id'); }

    public function availability(): BelongsTo { return $this->belongsTo(Availability::class); }

    public function preparedBy(): BelongsTo { return $this->belongsTo(User::class, 'prepared_user_id'); }
}
