<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstrumentAssetStatus: string implements HasLabel
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Rented = 'rented';
    case InWorkshop = 'in_workshop';
    case Sold = 'sold';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('cremona.instrument.status.'.$this->value);
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
