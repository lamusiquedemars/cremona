<?php

namespace App\Support;

final class ClientProfileOptions
{
    /** @return array<string, string> */
    public static function languages(): array
    {
        return [
            'fr' => 'Français',
            'en' => 'Anglais',
            'de' => 'Allemand',
            'es' => 'Espagnol',
            'it' => 'Italien',
            'pt' => 'Portugais',
        ];
    }

    /** @return array<string, string> */
    public static function countries(): array
    {
        return [
            'FR' => 'France',
            'BE' => 'Belgique',
            'CH' => 'Suisse',
            'LU' => 'Luxembourg',
            'MC' => 'Monaco',
            'CA' => 'Canada',
            'DE' => 'Allemagne',
            'AT' => 'Autriche',
            'ES' => 'Espagne',
            'IT' => 'Italie',
            'PT' => 'Portugal',
            'GB' => 'Royaume-Uni',
            'US' => 'États-Unis',
        ];
    }

    public static function languageLabel(?string $locale): string
    {
        return self::languages()[$locale] ?? $locale ?? '—';
    }

    public static function countryLabel(?string $countryCode): string
    {
        return self::countries()[$countryCode] ?? $countryCode ?? '—';
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'manual' => 'Créé dans Cremona',
            'maracuja-site' => 'Site web',
            'direct_email' => 'E-mail reçu',
            'import' => 'Import',
            default => $source ?? '—',
        };
    }
}
