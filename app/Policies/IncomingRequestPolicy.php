<?php

namespace App\Policies;

use App\Models\User;

class IncomingRequestPolicy extends CrmPolicy
{
    public function create(User $user): bool
    {
        return false;
    }
}
