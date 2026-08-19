<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MessageTransportStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Draft = 'draft';
    case Queued = 'queued';
    case Accepted = 'accepted';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Reçu',
            self::Draft => 'Brouillon',
            self::Queued => 'En attente d’envoi',
            self::Accepted => 'Pris en charge par le serveur',
            self::Failed => 'Échec d’envoi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received, self::Accepted => 'success',
            self::Draft, self::Queued => 'info',
            self::Failed => 'danger',
        };
    }
}
