<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AppointmentModality: string implements HasLabel
{
    case InPerson = 'in_person';
    case Video = 'video';
    case Phone = 'phone';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::InPerson => 'Sur place',
            self::Video => 'Visioconférence',
            self::Phone => 'Téléphone',
            self::Other => 'Autre',
        };
    }
}
