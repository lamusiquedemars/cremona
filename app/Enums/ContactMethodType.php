<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactMethodType: string implements HasLabel
{
    case Email = 'email';
    case Phone = 'phone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Phone => 'Téléphone',
        };
    }
}
