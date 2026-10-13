<?php

namespace App\Support;

final class InstrumentRentalCatalog
{
    public static function families(): array
    {
        return [
            'violon' => 'Violon',
            'alto' => 'Alto',
            'violoncelle' => 'Violoncelle',
            'contrebasse' => 'Contrebasse',
            'archet' => 'Archet',
            'autre' => 'Autre',
        ];
    }

    /**
     * Les tailles sont volontairement des codes stables : elles sont importables
     * et servent aussi à rapprocher un instrument d'une grille de location.
     */
    public static function sizesFor(?string $family): array
    {
        return match ($family) {
            'violon' => [
                '1/16' => '1/16', '1/10' => '1/10', '1/8' => '1/8', '1/4' => '1/4',
                '1/2' => '1/2', '3/4' => '3/4', '4/4' => '4/4', 'other' => 'Autre taille',
            ],
            'alto' => [
                '38cm' => '38 cm', '39cm' => '39 cm', '40cm' => '40 cm',
                '40_5cm' => '40,5 cm', '41cm' => '41 cm', '41_5cm' => '41,5 cm', '42cm' => '42 cm',
                'other' => 'Autre taille',
            ],
            'violoncelle' => [
                '1/10' => '1/10', '1/8' => '1/8', '1/4' => '1/4', '1/2' => '1/2',
                '3/4' => '3/4', '4/4' => '4/4', 'other' => 'Autre taille',
            ],
            'contrebasse' => [
                '1/8' => '1/8', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4',
                '4/4' => '4/4', 'other' => 'Autre taille',
            ],
            default => ['other' => 'Taille non standard'],
        };
    }

    public static function sizeLabel(?string $family, ?string $size): ?string
    {
        return self::sizesFor($family)[$size] ?? $size;
    }
}
