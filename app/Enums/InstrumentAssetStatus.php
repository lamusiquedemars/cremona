<?php

namespace App\Enums;

enum InstrumentAssetStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Rented = 'rented';
    case InWorkshop = 'in_workshop';
    case Sold = 'sold';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Reserved => 'Réservé',
            self::Rented => 'En location',
            self::InWorkshop => 'À l’atelier',
            self::Sold => 'Vendu',
            self::Archived => 'Archivé',
        };
    }
}
