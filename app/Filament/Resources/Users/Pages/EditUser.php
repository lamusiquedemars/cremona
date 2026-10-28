<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\Users\UserResource;

class EditUser extends BusinessEditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return UserResource::normalizeMembershipData($data);
    }
}
