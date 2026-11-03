<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class PasswordSetupLink extends BaseResetPassword
{
    public string $url;

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Créez votre mot de passe Cremona')
            ->greeting('Bonjour,')
            ->line('Un compte Cremona vient d’être créé pour vous.')
            ->line('Pour définir votre mot de passe et accéder à votre espace, cliquez sur le bouton ci-dessous.')
            ->action('Créer mon mot de passe', $this->resetUrl($notifiable))
            ->line('Ce lien est valable pendant '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail.')
            ->salutation('L’équipe Cremona');
    }

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
