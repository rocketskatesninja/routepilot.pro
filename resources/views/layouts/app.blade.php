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

        <!-- Favicon -->
        @php
            $customFavicon = \App\Models\Setting::getValue('favicon');
        @endphp
        @if($customFavicon)
            <link rel="icon" type="image/x-icon" href="{{ Storage::url($customFavicon) }}">
        @else
            <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
        @endif

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
    <body class="font-sans antialiased bg-base-100"
        style="
            @php
                $backgroundImage = \App\Models\Setting::getValue('background_image');
                $backgroundEnabled = \App\Models\Setting::getValue('background_enabled', '0');
                $backgroundFixed = \App\Models\Setting::getValue('background_fixed', '0');
            @endphp
            @if($backgroundImage && $backgroundEnabled)
                background-image: url('{{ Storage::url($backgroundImage) }}');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                background-attachment: {{ $backgroundFixed ? 'fixed' : 'scroll' }};
            @endif
        "
    >
        <!-- Global Alert Container -->
        <div id="global-alert-container" class="fixed top-6 right-6 z-50 flex flex-col space-y-4 items-end"></div>
        @if($backgroundImage && $backgroundEnabled)
            <div class="absolute inset-0 bg-base-100/80 backdrop-blur-sm pointer-events-none z-0"></div>
        @endif
        
        <div class="min-h-screen relative z-10">
            @include('layouts.navigation')

            <!-- Global Alert Trigger for Laravel Flash Messages -->
            @if(session('success'))
                <script>window.addEventListener('DOMContentLoaded',function(){showGlobalAlert({message: @json(session('success')), type: 'success'});});</script>
            @endif
            @if(session('error'))
                <script>window.addEventListener('DOMContentLoaded',function(){showGlobalAlert({message: @json(session('error')), type: 'error'});});</script>
            @endif
            @if(session('status'))
                <script>window.addEventListener('DOMContentLoaded',function(){showGlobalAlert({message: @json(session('status')), type: 'info'});});</script>
            @endif

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
        
        @yield('scripts')
    </body>
</html>
