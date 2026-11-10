<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

<title>
    {{ filled($title ?? null) && $title !== config('app.name') ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

{{-- Wyszukiwarki i podgląd linków (X, Facebook, WhatsApp): opis i obrazek z App\Support\Seo. --}}
@php
    $seo = \App\Support\Seo::current();
    $seoTitle = filled($title ?? null) && $title !== config('app.name') ? $title.' - '.config('app.name') : config('app.name');
    $noindex = $seo['noindex'] || request()->is('panel', 'panel/*', 'settings', 'settings/*', 'login', 'register', 'forgot-password', 'reset-password/*', 'email/*', 'user/*');
@endphp
<meta name="description" content="{{ $seo['description'] }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <link rel="canonical" href="{{ url()->current() }}">
@endif
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ app()->getLocale() === 'pl' ? 'pl_PL' : 'en_GB' }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta name="twitter:card" content="summary_large_image">
@if (filled(config('club.x_handle')))
    <meta name="twitter:site" content="{{ config('club.x_handle') }}">
@endif
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Motyw przed pierwszym malowaniem (bez mrugnięcia): zapisany wybór albo ustawienie telefonu, ten sam klucz co w app.js. --}}
<script>
    (() => {
        const apply = () => {
            let mode = 'system';
            try { mode = localStorage.getItem('flux.appearance') ?? 'system'; } catch (e) {}
            const dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        };
        apply();
        document.addEventListener('livewire:navigated', apply);
    })();
</script>
