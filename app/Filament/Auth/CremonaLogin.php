<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;

class CremonaLogin extends Login
{
    /**
     * Cremona est un outil privé utilisé au quotidien. L'utilisateur peut
     * décocher l'option sur un appareil partagé, mais un appareil personnel
     * reste connecté au-delà de l'expiration de sa session de travail.
     */
    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label(__('filament-panels::auth/pages/login.form.remember.label'))
            ->helperText('À laisser activé uniquement sur un appareil personnel.')
            ->default(true);
    }
}
