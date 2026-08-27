<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cremona:create-admin {email} {name?}', function (string $email, ?string $name = null): int {
    $password = $this->secret('Choisissez un mot de passe pour ce compte');

    if (! is_string($password) || $password === '') {
        $this->error('Aucun mot de passe n’a été défini.');

        return self::FAILURE;
    }

    User::query()->updateOrCreate([
        'email' => strtolower($email),
    ], [
        'name' => $name ?: $email,
        'password' => $password,
        'is_platform_admin' => true,
    ]);

    $this->info("Compte administrateur prêt pour {$email}.");

    return self::SUCCESS;
})->purpose('Crée ou met à jour un compte administrateur Cremona');
