<?php

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CampaignPolicy extends CrmPolicy
{
    public function update(User $user, Model $record): bool
    {
        return $record instanceof Campaign
            && $this->view($user, $record)
            && $record->status !== CampaignStatus::Archived
            && $this->canManage($user);
    }
}
