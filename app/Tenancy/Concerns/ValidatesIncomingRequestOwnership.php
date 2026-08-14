<?php

namespace App\Tenancy\Concerns;

use App\Models\IncomingRequest;
use LogicException;

trait ValidatesIncomingRequestOwnership
{
    public static function bootValidatesIncomingRequestOwnership(): void
    {
        static::saving(function ($model): void {
            if (! IncomingRequest::query()->whereKey($model->incoming_request_id)->exists()) {
                throw new LogicException('The incoming request does not belong to the active organization.');
            }
        });
    }
}
