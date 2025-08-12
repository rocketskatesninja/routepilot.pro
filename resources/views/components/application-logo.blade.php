@php
    $customLogo = \App\Models\Setting::getValue('logo');
@endphp

@if($customLogo)
    <img src="{{ asset(Storage::url($customLogo)) }}" alt="{{ \App\Models\Setting::getValue('site_title', 'RoutePilot Pro') }}" {{ $attributes }}>
@else
    <img src="{{ asset('images/logo.svg') }}" alt="{{ \App\Models\Setting::getValue('site_title', 'RoutePilot Pro') }}" {{ $attributes }}>
@endif
