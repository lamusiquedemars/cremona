<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Voided = 'voided';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon', self::Issued => 'Émise', self::PartiallyPaid => 'Partiellement réglée',
            self::Paid => 'Réglée', self::Overdue => 'En retard', self::Voided => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray', self::Issued => 'info', self::PartiallyPaid => 'warning',
            self::Paid => 'success', self::Overdue => 'danger', self::Voided => 'gray',
        };
    }
}
