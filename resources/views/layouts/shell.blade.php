{{--
    Skorupa aplikacji 2.0 (przebudowa): górny pasek, treść i dolny pasek nawigacji jak w aplikacji na telefon.
    Bez Flux: tylko własne komponenty x-ui.*, Tailwind i Alpine. Strony przechodzą tu po kolei (etap C i dalej).
--}}
@props(['title' => null])

@php
    $user = auth()->user();
    $items = [
        ['key' => 'start', 'label' => __('Start'), 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['key' => 'results', 'label' => __('Results'), 'href' => route('results'), 'active' => request()->routeIs('results')],
        ['key' => 'tip', 'label' => __('Make a tip'), 'href' => route('tips'), 'active' => request()->routeIs('tips')],
        ['key' => 'club', 'label' => __('My club'), 'href' => $user ? route('team.show', $user) : route('login'), 'active' => request()->routeIs('team.show') && request()->route('user')?->is($user)],
        ['key' => 'more', 'label' => __('More'), 'href' => null, 'active' => false],
    ];
    // Kolejność zakładek dla przesuwania palcem (bez „Więcej”).
    $swipe = collect($items)->whereNotNull('href')->values();
    $current = $swipe->search(fn ($item) => $item['active']);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
    @include('partials.club-theme')
    <style>
        /* Przejścia między stronami skorupy (obie strony muszą to mieć, więc stare widoki się nie zmieniają). */
        @view-transition {
            navigation: auto;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="ui-shell min-h-dvh bg-ui-bg font-ui text-ui-ink antialiased" x-data>
    <x-maintenance-notice />

    <header class="sticky top-0 z-20 bg-ui-bg/90 backdrop-blur-md">
        <div class="mx-auto flex max-w-xl items-center gap-2.5 px-4 pb-2 pt-[calc(1rem+env(safe-area-inset-top))]">
            <a href="{{ route('home') }}" wire:navigate class="flex flex-1 items-center gap-2.5">
                <img src="{{ asset(config('club.logo')) }}" alt="" class="size-8 object-contain">
                <span class="font-display text-[21px] font-extrabold tracking-tight">{{ config('club.brand.prefix') }}{{ config('club.brand.suffix') }}</span>
            </a>
            {{ $actions ?? '' }}
        </div>
    </header>

    <main class="mx-auto flex max-w-xl flex-col gap-3.5 px-3.5 pb-32 pt-1 [view-transition-name:ui-main]"
        x-data="uiSwipeNav(@js($swipe->pluck('href')), @js($current === false ? -1 : $current))"
        @touchstart.passive="start($event)" @touchend="end($event)">
        <x-ui.toast />
        {{ $slot }}
    </main>

    <x-ui.bottom-nav :items="$items" />
    <x-ui.more-sheet />
</body>

</html>
