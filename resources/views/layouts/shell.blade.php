{{--
    Skorupa aplikacji 2.0 (przebudowa): górny pasek, treść i dolny pasek nawigacji jak w aplikacji na telefon.
    Na komputerze (lg) dolny pasek zamienia się w menu w nagłówku, a treść dostaje szerszą kolumnę.
    legacy = stara strona publiczna: wyszukiwarka w nagłówku i wspólne okna (pojedynek, mecz Lecha). Strony nie używają już Flux (etap J).
--}}
@props(['title' => null, 'legacy' => false, 'aside' => null])

@php
    $user = auth()->user();
    $items = [
        ['key' => 'start', 'label' => __('Start'), 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['key' => 'results', 'label' => __('Results'), 'href' => route('results'), 'active' => request()->routeIs('results')],
        ['key' => 'tip', 'label' => __('Make a tip'), 'href' => $user ? route('tips') : route('login'), 'active' => request()->routeIs('tips')],
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
        /* Przejścia między stronami skorupy (obie strony muszą to mieć, więc panel admina się nie zmienia). */
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

    <header class="sticky top-0 z-20 border-b border-transparent bg-ui-bg/90 backdrop-blur-md lg:border-ui-line">
        <div class="mx-auto flex max-w-6xl items-center gap-2.5 px-4 pb-2 pt-[calc(1rem+env(safe-area-inset-top))] lg:pb-3 lg:pt-3">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5">
                <img src="{{ asset(config('club.logo')) }}" alt="" class="size-8 object-contain">
                <span class="font-display text-[21px] font-extrabold tracking-tight">{{ config('club.brand.prefix') }}{{ config('club.brand.suffix') }}</span>
            </a>

            {{-- Komputer: te same pozycje co dolny pasek, w nagłówku. --}}
            <nav class="ms-6 hidden flex-1 items-center gap-1 lg:flex" aria-label="{{ __('Main menu') }}">
                @foreach ($items as $item)
                    @if ($item['href'])
                        <a href="{{ $item['href'] }}" wire:navigate @class([
                            'rounded-xl px-3 py-2 text-[15px] font-semibold',
                            'bg-club text-club-ink' => $item['active'],
                            'text-ui-muted hover:bg-ui-raised hover:text-ui-ink' => ! $item['active'],
                        ]) @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @else
                        <button type="button" @click="$dispatch('ui-sheet', { name: 'more' })"
                            class="rounded-xl px-3 py-2 text-[15px] font-semibold text-ui-muted hover:bg-ui-raised hover:text-ui-ink">{{ $item['label'] }}</button>
                    @endif
                @endforeach
            </nav>
            <span class="flex-1 lg:hidden"></span>

            @if ($legacy)
                <livewire:pages::home.global-search />
            @endif
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-6 px-3.5 lg:px-6">
        <main class="flex min-w-0 flex-1 flex-col gap-3.5 pb-32 pt-1 lg:pb-12 lg:pt-6 [view-transition-name:ui-main]"
            x-data="uiSwipeNav(@js($swipe->pluck('href')), @js($current === false ? -1 : $current))"
            @touchstart.passive="start($event)" @touchend="end($event)">
            <x-ui.toast />
            {{ $slot }}
            <x-public.footer />
        </main>

        {{-- Komputer: kolumna z rywalami, newsami i premium (na telefonie jest w menu „Więcej”). --}}
        @if ($aside)
            <aside class="sticky top-20 hidden w-72 shrink-0 self-start pt-6 xl:block">{{ $aside }}</aside>
        @endif
    </div>

    <div class="lg:hidden">
        <x-ui.bottom-nav :items="$items" />
    </div>
    <x-ui.more-sheet :extra="$aside" />
    <x-ui.confirm />

    @if ($legacy)
        {{-- Wspólne okna stron publicznych: pojedynek i mecz Lecha. --}}
        <livewire:pages::home.duel-modal />
        <livewire:pages::home.lech-match-modal />
    @endif
</body>

</html>
