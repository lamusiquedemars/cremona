<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuoteStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon', self::Sent => 'Envoyé', self::Accepted => 'Accepté', self::Declined => 'Refusé', self::Expired => 'Expiré',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray', self::Sent => 'info', self::Accepted => 'success', self::Declined => 'danger', self::Expired => 'warning',
        };
    }
}
