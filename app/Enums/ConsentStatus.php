<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConsentStatus: string implements HasColor, HasLabel
{
    case Unknown = 'unknown';
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unknown => 'Non renseigné',
            self::Granted => 'Accordé',
            self::Withdrawn => 'Retiré',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Granted => 'success',
            self::Withdrawn => 'danger',
            self::Unknown => 'gray',
        };
    }
}
