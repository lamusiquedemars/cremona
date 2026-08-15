<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Planifié',
            self::Completed => 'Terminé',
            self::Cancelled => 'Annulé',
            self::NoShow => 'Absent',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Completed => 'success',
            self::Cancelled, self::NoShow => 'gray',
        };
    }
}
