<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="
    // Set initial theme before page renders to prevent flash
    darkMode = localStorage.getItem('darkMode') === 'true' || (localStorage.getItem('darkMode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    if (darkMode) {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.setAttribute('data-theme', 'light');
    }
    
    $watch('darkMode', val => {
        localStorage.setItem('darkMode', val);
        if (val) {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })
">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Prevent theme flash -->
        <script>
            (function() {
                const savedTheme = localStorage.getItem('darkMode');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const shouldUseDark = savedTheme === 'true' || (savedTheme === null && prefersDark);
                
                if (shouldUseDark) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-base-100">
        <div class="min-h-screen">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-base-100 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                @yield('content')
            </main>
        </div>
    </body>
</html>
