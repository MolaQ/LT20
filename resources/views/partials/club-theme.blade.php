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
