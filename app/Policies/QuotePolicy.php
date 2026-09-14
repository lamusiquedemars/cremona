<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class QuotePolicy extends CrmPolicy
{
    public function delete(User $user, Model $quote): bool
    {
        return $quote instanceof \App\Models\Quote
            && $quote->status === QuoteStatus::Draft
            && $this->update($user, $quote);
    }
}
