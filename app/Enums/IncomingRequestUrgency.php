<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncomingRequestUrgency: string implements HasColor, HasLabel
{
    case Unknown = 'unknown';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unknown => 'Non renseignée',
            self::Normal => 'Normale',
            self::High => 'Élevée',
            self::Urgent => 'Urgente',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unknown => 'gray',
            self::Normal => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }
}
