<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkshopOrderStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Diagnosed = 'diagnosed';
    case AwaitingApproval = 'awaiting_approval';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Reçu', self::Diagnosed => 'Diagnostic établi', self::AwaitingApproval => 'En attente d’accord', self::Scheduled => 'Planifié', self::InProgress => 'En cours', self::Ready => 'Prêt à restituer', self::Returned => 'Restitué', self::Cancelled => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'gray', self::Diagnosed => 'info', self::AwaitingApproval => 'warning', self::Scheduled => 'primary', self::InProgress => 'primary', self::Ready => 'success', self::Returned => 'gray', self::Cancelled => 'danger',
        };
    }
}
