<?php

namespace App\Enums;

enum IncomingRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Qualified = 'qualified';
    case Closed = 'closed';

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
