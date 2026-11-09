{{-- Pierścień procentowy (statystyki). Wypełnia się animacją po wejściu na ekran. color: club, gold, good. --}}
@props(['value' => 0, 'label' => '', 'color' => 'club'])

@php
    $c = 2 * M_PI * 38;
    $offset = $c * (1 - max(0, min(100, $value)) / 100);
    $stroke = match ($color) {
        'gold' => 'stroke-gold',
        'good' => 'stroke-good',
        default => 'stroke-club',
    };
@endphp

<div x-data="{ on: false }" x-intersect.once="on = true" {{ $attributes->class('flex flex-col items-center gap-1.5 text-center') }}>
    <div class="relative size-22">
        <svg viewBox="0 0 88 88" class="size-full -rotate-90" aria-hidden="true">
            <circle cx="44" cy="44" r="38" fill="none" stroke-width="9" class="stroke-ui-raised" />
            <circle cx="44" cy="44" r="38" fill="none" stroke-width="9" stroke-linecap="round" class="{{ $stroke }} transition-[stroke-dashoffset] duration-[1200ms] ease-out"
                stroke-dasharray="{{ $c }}" :stroke-dashoffset="on ? {{ $offset }} : {{ $c }}" />
        </svg>
        <span class="ui-num absolute inset-0 grid place-items-center text-[22px] font-extrabold">{{ $value }}%</span>
    </div>
    <span class="text-xs leading-tight text-ui-muted">{{ $label }}</span>
</div>
