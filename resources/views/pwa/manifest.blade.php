@php($pwa = config('cremona.pwa'))
{!! json_encode([
    'id' => $pwa['start_url'],
    'name' => $pwa['name'],
    'short_name' => $pwa['short_name'],
    'description' => $pwa['description'],
    'lang' => str_replace('_', '-', app()->getLocale()),
    'start_url' => $pwa['start_url'],
    'scope' => $pwa['scope'],
    'display' => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone'],
    'theme_color' => $pwa['theme_color'],
    'background_color' => $pwa['background_color'],
    'icons' => [
        ['src' => asset($pwa['icon_192']), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => asset($pwa['icon_512']), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
