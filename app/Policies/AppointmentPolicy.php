<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AppointmentPolicy extends CrmPolicy
{
    public function create(User $user): bool
    {
        return parent::create($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $record->getAttribute('provider') === 'manual'
            && parent::update($user, $record);
    }
}
