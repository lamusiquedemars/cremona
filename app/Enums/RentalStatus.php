<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentalStatus: string implements HasLabel
{
    case Preparing = 'preparing';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case HandedOver = 'handed_over';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('cremona.rental.status.'.$this->value);
    }
}
