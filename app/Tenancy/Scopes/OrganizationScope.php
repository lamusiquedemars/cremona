<?php

namespace App\Tenancy\Scopes;

use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(OrganizationContext::class)->id();

        if ($organizationId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where(
            $model->qualifyColumn($model->getOrganizationColumn()),
            $organizationId,
        );
    }
}
