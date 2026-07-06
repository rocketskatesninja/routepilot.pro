<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="preload"@if (! empty($brandChannels)) style="--brand: {{ $brandChannels }}; --primary: {{ $brandChannels }}; --ring: {{ $brandChannels }};"@endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Apply the saved theme before first paint so dark mode never flashes white on load/refresh. --}}
        <script>
            (function () {
                try {
                    var a = localStorage.getItem('appearance');
                    if (a === 'dark' || ((!a || a === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Critical base styles, applied before app.css loads, so a slow CSS fetch
             can't flash a white background or an unsized (page-filling) logo. --}}
        <style>
            html { background-color: #ffffff; color-scheme: light; }
            html.dark { background-color: #0d1017; color-scheme: dark; }
        </style>

        {{-- Title is managed entirely by Inertia (@inertiaHead) so SSR emits a single
             <title>. A static one here would duplicate it and, being first, win over
             the real per-page title for crawlers. --}}

        {{-- Browser tab / bookmark icon (SVG for modern browsers, PNG fallback). --}}
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/pwa/icon-192.png">

        {{-- Installable PWA: manifest, theme color, and home-screen icons. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0ea5e9">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="RoutePilot">
        <link rel="apple-touch-icon" href="/assets/images/pwa/icon-192.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead

        {{-- Structured data (JSON-LD) for pages that provide it (e.g. the marketing
             homepage) — server-rendered so crawlers see it without running JS. --}}
        @php($jsonLd = data_get($page ?? [], 'props.jsonLd'))
        @if (! empty($jsonLd))
            <script type="application/ld+json">{!! $jsonLd !!}</script>
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Register the service worker in production only (HTTPS-gated; avoids
             clobbering Vite HMR during local dev). --}}
        @production
            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', function () {
                        navigator.serviceWorker.register('/sw.js').catch(function () {});
                    });
                }
            </script>
        @endproduction
    </body>
</html>
