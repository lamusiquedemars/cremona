<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncomingRequestStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Qualified = 'qualified';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nouvelle',
            self::InProgress => 'En cours',
            self::WaitingCustomer => 'En attente du contact',
            self::Qualified => 'Qualifiée',
            self::Closed => 'Clôturée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InProgress => 'warning',
            self::WaitingCustomer => 'gray',
            self::Qualified => 'success',
            self::Closed => 'gray',
        };
    }

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Qualified, self::Closed],
            self::InProgress => [self::WaitingCustomer, self::Qualified, self::Closed],
            self::WaitingCustomer => [self::InProgress, self::Qualified, self::Closed],
            self::Qualified => [self::InProgress, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
