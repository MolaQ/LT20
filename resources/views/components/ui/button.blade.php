{{-- Przycisk 2.0: duży, do kciuka. variant: primary (barwa klubu), ghost (tło karty), light (biały na banerze). Z href jest linkiem z wire:navigate. --}}
@props(['variant' => 'primary', 'href' => null, 'size' => 'lg'])

@php
    $classes = [
        'inline-flex w-full items-center justify-center gap-2 rounded-2xl font-semibold transition active:scale-[.97] focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-gold disabled:opacity-50',
        $size === 'lg' ? 'px-4 py-3 text-base' : 'px-3 py-2 text-sm',
        match ($variant) {
            'ghost' => 'bg-ui-raised text-ui-ink',
            'light' => 'bg-white text-[#0b2f6e]',
            default => 'bg-club text-club-ink ui-glow',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>{{ $slot }}</button>
@endif
