{{-- Barwy klubu z config/club.php jako zmienne CSS (--club-*). Inna drużyna = inne wartości w .env. --}}
@php($colors = config('club.colors'))
<style>
    :root {
        --club-primary: {{ $colors['primary'] }};
        --club-primary-dark: {{ $colors['primary_dark'] }};
        --club-deep: {{ $colors['deep'] }};
        --club-accent: {{ $colors['accent'] }};
    }
</style>
<meta name="theme-color" content="{{ $colors['deep'] }}">
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('club.brand.prefix').config('club.brand.suffix') }}">
