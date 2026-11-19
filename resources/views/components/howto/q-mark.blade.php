{{--
    Znak odpowiedzi na pytanie w poradniku, jak na stronie typowania: trafienie to miecze (atak) albo tarcza (obrona)
    w kolorach stron, pudło to jasnoczerwony kafelek z krzyżykiem i małym symbolem strony w rogu (żeby było widać,
    który bonus zeruje), pominięte to pusty kafelek ze znakiem zapytania.
    mark: attack, defense, miss-attack, miss-defense, skip.
--}}
@props(['mark', 'small' => false])

@php
    $side = str_ends_with($mark, 'defense') ? 'defense' : 'attack';
    $icon = $side === 'defense' ? 'shield' : 'swords';
    $box = $small ? 'size-5 rounded' : 'size-8 rounded-lg';
    $glyph = $small ? 'size-3' : 'size-4';
@endphp

@if ($mark === 'skip')
    <span {{ $attributes->class([$box, 'grid shrink-0 place-items-center border-[1.5px] border-dashed border-ui-muted/60 font-bold text-ui-muted', 'text-[10px]' => $small, 'text-sm' => ! $small]) }} title="{{ __('skipped') }}">?</span>
@elseif (str_starts_with($mark, 'miss'))
    <span {{ $attributes->class([$box, 'relative grid shrink-0 place-items-center border-[1.5px] border-red-300 bg-red-50 font-black leading-none text-red-500 dark:border-red-400/60 dark:bg-red-500/15 dark:text-red-300', 'text-[11px]' => $small, 'text-lg' => ! $small]) }} title="{{ $side === 'defense' ? __('miss, defence') : __('miss, attack') }}">
        ✕
        @unless ($small)
            <span class="absolute -bottom-1.5 -right-1.5 grid size-4 place-items-center rounded-full text-white ring-2 ring-ui-surface" style="background: var(--q-{{ $side }})"><x-ui.icon :name="$icon" variant="micro" class="size-2.5" /></span>
        @endunless
    </span>
@else
    <span {{ $attributes->class([$box, 'grid shrink-0 place-items-center text-white shadow-sm']) }} style="background: var(--q-{{ $side }})" title="{{ $side === 'defense' ? __('hit, defence') : __('hit, attack') }}">
        <x-ui.icon :name="$icon" variant="micro" :class="$glyph" />
    </span>
@endif
