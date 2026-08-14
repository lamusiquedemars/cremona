<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncomingRequestOutcome: string implements HasColor, HasLabel
{
    case Answered = 'answered';
    case Converted = 'converted';
    case NoFollowUp = 'no_follow_up';
    case Spam = 'spam';
    case Duplicate = 'duplicate';

    public function getLabel(): string
    {
        return match ($this) {
            self::Answered => 'Réponse apportée',
            self::Converted => 'Convertie',
            self::NoFollowUp => 'Sans suite',
            self::Spam => 'Indésirable',
            self::Duplicate => 'Doublon',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Converted => 'success',
            self::Answered => 'info',
            self::NoFollowUp, self::Duplicate => 'gray',
            self::Spam => 'danger',
        };
    }
}
