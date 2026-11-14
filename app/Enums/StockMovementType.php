<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Consumption = 'consumption';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Entrée',
            self::Consumption => 'Consommation atelier',
            self::Sale => 'Vente',
            self::Return => 'Retour en stock',
            self::Adjustment => 'Correction d’inventaire',
        };
    }
}
