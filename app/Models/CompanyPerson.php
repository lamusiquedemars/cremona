<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['company_id', 'person_id', 'job_title', 'is_primary', 'started_at', 'ended_at'])]
class CompanyPerson extends Model
{
    use BelongsToOrganization;

    protected $table = 'company_person';

    protected static function booted(): void
    {
        static::saving(function (CompanyPerson $relationship): void {
            if (! Company::query()->whereKey($relationship->company_id)->exists()) {
                throw new LogicException('The company does not belong to the active organization.');
            }

            if (! Person::query()->whereKey($relationship->person_id)->exists()) {
                throw new LogicException('The person does not belong to the active organization.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'started_at' => 'immutable_date',
            'ended_at' => 'immutable_date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
