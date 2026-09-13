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
