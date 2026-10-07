<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentalDocumentStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Generated => 'Généré',
            self::Superseded => 'Remplacé',
            self::Cancelled => 'Annulé',
        };
    }
}
