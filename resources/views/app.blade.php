<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"@if (! empty($brandChannels)) style="--brand: {{ $brandChannels }}; --primary: {{ $brandChannels }}; --ring: {{ $brandChannels }};"@endif>
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

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
