<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentalDocumentType: string implements HasLabel
{
    case RentalContract = 'rental_contract';
    case InsuranceContract = 'insurance_contract';
    case ReturnCertificate = 'return_certificate';

    public function getLabel(): string
    {
        return match ($this) {
            self::RentalContract => 'Contrat de location',
            self::InsuranceContract => 'Contrat d’assurance',
            self::ReturnCertificate => 'Attestation de restitution',
        };
    }
}
