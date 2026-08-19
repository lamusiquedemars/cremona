<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConversationStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case WaitingCustomer = 'waiting_customer';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'À traiter',
            self::WaitingCustomer => 'En attente du client',
            self::Closed => 'Clôturée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::WaitingCustomer => 'info',
            self::Closed => 'gray',
        };
    }
}
