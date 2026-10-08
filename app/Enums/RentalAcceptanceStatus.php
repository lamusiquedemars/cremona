<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentalAcceptanceStatus: string implements HasLabel
{
    case Created = 'created';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Préparée',
            self::Sent => 'Envoyée',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
            self::Expired => 'Expirée',
            self::Cancelled => 'Annulée',
        };
    }
}
