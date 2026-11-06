<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Langues d’interface des organisations
    |--------------------------------------------------------------------------
    |
    | La langue est rattachée à l’organisation afin que chaque espace de
    | travail conserve son interface, indépendamment de la langue de la
    | plateforme Cremona.
    |
    */
    'interface_locales' => [
        'fr' => 'Français',
        'pt_BR' => 'Português (Brasil)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Portail Site → Cremona
    |--------------------------------------------------------------------------
    |
    | Les clients OAuth publics sont enregistrés par site. Le client id n'est
    | pas un secret : PKCE protège l'échange du code. Cette table de
    | correspondance lie un client à une seule organisation.
    |
    */
    'site_portal' => [
        'clients' => array_filter([
            env('CREMONA_SSO_DEMO_SITE_CLIENT_ID') => [
                'organization' => 'site.maracujadigital.fr',
                'site_name' => 'Maracuja Digital',
                'site_url' => 'https://site.maracujadigital.fr/admin',
                'logout_redirect_uri' => 'https://site.maracujadigital.fr/admin/sso/logout/complete',
                'logout_secret' => env('CREMONA_SSO_DEMO_SITE_LOGOUT_SECRET'),
                'items' => ['clients', 'requests', 'appointments', 'quotes'],
            ],
        ], fn (string $clientId): bool => filled($clientId), ARRAY_FILTER_USE_KEY),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identité PWA
    |--------------------------------------------------------------------------
    |
    | Ces valeurs sont volontairement centralisées : une installation dédiée à
    | un client pourra changer son nom et ses icônes via l'environnement, sans
    | modifier les panels Filament ni le manifeste.
    |
    */
    'pwa' => [
        'name' => env('PWA_APP_NAME', env('APP_NAME', 'Cremona')),
        'short_name' => env('PWA_APP_SHORT_NAME', 'Cremona'),
        'description' => env('PWA_APP_DESCRIPTION', 'L’espace de pilotage Cremona.'),
        'start_url' => env('PWA_START_URL', '/dashboard'),
        'scope' => env('PWA_SCOPE', '/'),
        'theme_color' => env('PWA_THEME_COLOR', '#f59e0b'),
        'background_color' => env('PWA_BACKGROUND_COLOR', '#faf8f3'),
        'icon_192' => env('PWA_ICON_192', '/pwa/icons/cremona-192.png'),
        'icon_512' => env('PWA_ICON_512', '/pwa/icons/cremona-512.png'),
        'apple_touch_icon' => env('PWA_APPLE_TOUCH_ICON', '/pwa/icons/cremona-180.png'),
    ],
];
