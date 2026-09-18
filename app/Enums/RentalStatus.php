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
        return __('cremona.rental.status.'.$this->value);
    }
}
