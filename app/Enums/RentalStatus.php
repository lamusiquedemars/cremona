<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentalStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'À préparer',
            self::Active => 'En cours',
            self::Returned => 'Restituée',
            self::Cancelled => 'Annulée',
        };
    }
}
