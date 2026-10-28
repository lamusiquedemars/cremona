<?php

namespace App\Services;

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use LogicException;

final class UserPasswordSetupLink
{
    public function send(User $user): void
    {
        if (! $user->isActive()) {
            throw new LogicException('Un compte suspendu ne peut pas recevoir de lien d’accès.');
        }

        $panel = Filament::getPanel($user->is_platform_admin ? 'platform' : 'admin');

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $user->email],
            function (CanResetPassword $notifiable, string $token) use ($panel): void {
                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = $panel->getResetPasswordUrl($token, $notifiable);
                $notifiable->notify($notification);
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw new LogicException('Le lien d’accès n’a pas pu être envoyé.');
        }

        $user->forceFill(['invited_at' => now()])->saveQuietly();
    }
}
