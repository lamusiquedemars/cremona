<?php

namespace App\Support;

final class ClientSearchCatalog
{
    /** @return array<string, string> */
    public static function objectTypes(): array
    {
        return [
            'violon' => 'Violon',
            'alto' => 'Alto / viole',
            'violoncelle' => 'Violoncelle',
            'contrebasse' => 'Contrebasse / basse',
            'archet' => 'Archet',
            'corde' => 'Corde',
            'colophane' => 'Colophane',
            'accessoire' => 'Accessoire',
        ];
    }

    public static function usesInstrumentSizes(?string $objectType): bool
    {
        return in_array($objectType, ['violon', 'alto', 'violoncelle', 'contrebasse'], true);
    }

    /** @return array<string, string> */
    public static function sizesFor(?string $objectType): array
    {
        return self::usesInstrumentSizes($objectType)
            ? InstrumentRentalCatalog::sizesFor($objectType)
            : [];
    }

    public static function label(?string $objectType): ?string
    {
        return self::objectTypes()[$objectType] ?? $objectType;
    }
}
