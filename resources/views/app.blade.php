<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- ============ PWA tags ============
             - manifest: tells browsers this site is installable
             - theme-color: tints the Android status bar / desktop title bar
             - apple-* tags: makes iOS treat installed PWA as a real app
             - Standard favicon for browser tabs
        --}}
        <link rel="manifest" href="/build/manifest.webmanifest">
        <meta name="theme-color" content="#047857">
        <link rel="icon" type="image/png" href="/pwa-192x192.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="GBHSS Skardu">
        <meta name="mobile-web-app-capable" content="yes">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
