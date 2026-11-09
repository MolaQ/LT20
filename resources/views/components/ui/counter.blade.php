{{-- Licznik +/− zamiast pola tekstowego (np. gole w typie). Wiązanie: <x-ui.counter wire:model.live="home" />. --}}
@props(['label' => null, 'min' => 0, 'max' => 9, 'onHero' => true])

@php($key = $onHero ? 'bg-white/15' : 'bg-ui-raised')

<div x-data="uiCounter({{ (int) $min }}, {{ (int) $max }})" x-modelable="value" {{ $attributes->whereStartsWith('wire:model') }}
    {{ $attributes->whereDoesntStartWith('wire:model')->class('flex flex-col items-center gap-2') }}>
    @if ($label)
        <span class="font-display text-lg font-extrabold">{{ $label }}</span>
    @endif
    <span class="ui-num min-w-14 text-center text-7xl font-extrabold leading-none" :class="bump && 'animate-ui-bump'" x-text="value"
        aria-live="polite"></span>
    <div class="flex gap-2">
        <button type="button" @click="change(-1)" :disabled="value <= {{ (int) $min }}"
            class="grid size-12 place-items-center rounded-xl {{ $key }} text-2xl font-bold transition active:bg-club active:text-club-ink disabled:opacity-40"
            aria-label="{{ __('Less') }}">−</button>
        <button type="button" @click="change(1)" :disabled="value >= {{ (int) $max }}"
            class="grid size-12 place-items-center rounded-xl {{ $key }} text-2xl font-bold transition active:bg-club active:text-club-ink disabled:opacity-40"
            aria-label="{{ __('More') }}">+</button>
    </div>
</div>
