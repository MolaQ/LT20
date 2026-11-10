{{-- Nagłówek (zamiennik flux:heading). size: base, lg, xl; level: 1-4 (bez level to zwykły div, jak we Flux). --}}
@props(['size' => 'base', 'level' => null])

@php
    $tag = $level ? 'h'.max(1, min(4, (int) $level)) : 'div';
@endphp

<{{ $tag }} {{ $attributes->class([
    'font-display font-extrabold tracking-tight text-ui-ink',
    match ($size) { 'xl' => 'text-[26px] uppercase leading-tight', 'lg' => 'text-xl', default => 'text-[17px]' },
]) }}>{{ $slot }}</{{ $tag }}>
