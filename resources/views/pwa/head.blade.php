@php($pwa = config('cremona.pwa'))
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ $pwa['theme_color'] }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $pwa['short_name'] }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset($pwa['apple_touch_icon']) }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset($pwa['icon_192']) }}">
