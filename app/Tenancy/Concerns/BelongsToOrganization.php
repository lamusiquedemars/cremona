<?php

namespace App\Tenancy\Concerns;

use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            $contextId = app(OrganizationContext::class)->id();

            if ($model->getAttribute($model->getOrganizationColumn()) === null && $contextId === null) {
                throw new LogicException('Tenant-scoped records require an active organization.');
            }

            if ($contextId !== null) {
                $model->setAttribute($model->getOrganizationColumn(), $contextId);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getOrganizationColumn(): string
    {
        return 'organization_id';
    }
}
