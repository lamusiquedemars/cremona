<?php

namespace App\Enums;

enum RentalStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'À préparer',
            self::Active => 'En cours',
            self::Returned => 'Restituée',
            self::Cancelled => 'Annulée',
        };
    }
}
