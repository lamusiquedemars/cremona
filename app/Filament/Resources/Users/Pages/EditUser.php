<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changePassword')
                ->label('Changer le mot de passe')
                ->modalHeading('Changer le mot de passe')
                ->modalDescription('Les sessions persistantes de cet utilisateur seront fermées pour protéger le compte.')
                ->form([
                    TextInput::make('password')
                        ->label('Nouveau mot de passe')
                        ->password()
                        ->revealable()
                        ->required()
                        ->confirmed(),
                    TextInput::make('password_confirmation')
                        ->label('Confirmer le nouveau mot de passe')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->forceFill([
                        'password' => Hash::make($data['password']),
                        'remember_token' => Str::random(60),
                    ])->save();

                    Notification::make()
                        ->success()
                        ->title('Mot de passe mis à jour')
                        ->body('L’utilisateur peut se reconnecter avec son nouveau mot de passe.')
                        ->send();
                }),
        ];
    }
}
