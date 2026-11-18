{{--
    „Postaw kawkę” (18.11.2026): przycisk wsparcia projektu z parującym kubkiem. Prowadzi prosto na buycoffee.to
    (Payment::buyCoffeeUrl), a bez ustawionego adresu na stronę wsparcia. Kawka to dobrowolne wsparcie, nie daje premium.
    Warianty: card (prawy panel, karta z podziękowaniem), banner (pas na stronie głównej), pill (stopka, mały przycisk),
    tile (kafelek w menu „Więcej”).
--}}
@props(['variant' => 'pill'])

@php
    $coffeeUrl = \App\Models\Payment::buyCoffeeUrl();
    $href = $coffeeUrl ?? route('support');
    $external = $coffeeUrl !== null;
    $linkAttrs = $external ? 'target="_blank" rel="noopener"' : 'wire:navigate';
@endphp

@if ($variant === 'card')
    <div {{ $attributes->class('coffee-card relative overflow-hidden rounded-2xl p-4 text-amber-950 shadow-sm') }}>
        <div class="flex items-start gap-3">
            <x-coffee-cup class="size-14 shrink-0" />
            <div class="min-w-0 space-y-1">
                <p class="font-display text-lg font-bold leading-tight">{{ __('Do you like LechTYPER?') }}</p>
                <p class="text-sm leading-snug text-amber-900/80">{{ __('The game is free and made by a fan after hours. A coffee keeps the server running and new features coming.') }}</p>
            </div>
        </div>
        <a href="{{ $href }}" {!! $linkAttrs !!}
            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-amber-950 px-4 py-2.5 text-sm font-bold text-amber-50 shadow transition hover:-translate-y-0.5 hover:bg-amber-900">
            <span aria-hidden="true">☕</span> {{ __('Buy us a coffee') }}
        </a>
        <a href="{{ route('support') }}#sciana" class="mt-2 block text-center text-xs font-semibold text-amber-900/70 underline-offset-2 hover:underline">{{ __('Supporters wall') }}</a>
    </div>
@elseif ($variant === 'banner')
    <div {{ $attributes->class('coffee-card relative flex flex-col items-center gap-4 overflow-hidden rounded-2xl px-5 py-4 text-amber-950 shadow-sm sm:flex-row') }}>
        <x-coffee-cup class="size-16 shrink-0" />
        <div class="min-w-0 flex-1 text-center sm:text-left">
            <p class="font-display text-lg font-bold leading-tight">{{ __('Do you like LechTYPER?') }}</p>
            <p class="text-sm text-amber-900/80">{{ __('The game is free and made by a fan after hours. A coffee keeps the server running and new features coming.') }}</p>
        </div>
        <a href="{{ $href }}" {!! $linkAttrs !!}
            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-amber-950 px-5 py-3 text-sm font-bold text-amber-50 shadow transition hover:-translate-y-0.5 hover:bg-amber-900">
            <span aria-hidden="true">☕</span> {{ __('Buy us a coffee') }}
        </a>
    </div>
@elseif ($variant === 'tile')
    <a href="{{ $href }}" {!! $linkAttrs !!} {{ $attributes->class('coffee-card group col-span-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-amber-950 shadow-sm transition hover:-translate-y-0.5') }}>
        <x-coffee-cup class="size-9 shrink-0" />
        <span class="flex-1 leading-tight">{{ __('Buy us a coffee') }}<span class="block text-xs font-medium text-amber-900/75">{{ __('Support the project') }}</span></span>
        <x-ui.icon name="heart" variant="mini" class="size-5 text-red-600 motion-safe:group-hover:scale-110" />
    </a>
@else
    <a href="{{ $href }}" {!! $linkAttrs !!} {{ $attributes->class('coffee-card group inline-flex items-center gap-2 rounded-full py-1.5 pe-4 ps-1.5 text-sm font-bold text-amber-950 shadow-sm transition hover:-translate-y-0.5') }}>
        <x-coffee-cup class="size-8 shrink-0" />
        {{ __('Buy us a coffee') }}
    </a>
@endif
