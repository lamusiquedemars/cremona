<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserPasswordSetupLink;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Throwable;

class CreateUser extends BusinessCreateRecord
{
    protected static string $resource = UserResource::class;

    private bool $sendPasswordSetupLink = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->sendPasswordSetupLink = (bool) ($data['send_password_setup_link'] ?? true);
        unset($data['send_password_setup_link']);

        if (blank($data['password'] ?? null)) {
            $data['password'] = Str::random(64);
        }

        return UserResource::normalizeMembershipData($data);
    }

    protected function afterCreate(): void
    {
        if (! $this->sendPasswordSetupLink || ! $this->record instanceof User) {
            return;
        }

        try {
            app(UserPasswordSetupLink::class)->send($this->record);
        } catch (Throwable) {
            Notification::make()->danger()
                ->title('Compte créé, mais l’e-mail n’a pas pu être envoyé')
                ->body('Utilisez « Envoyer le lien d’accès » depuis la fiche utilisateur après avoir vérifié la messagerie.')
                ->persistent()->send();
        }
    }
}
