{{-- Nagłówek (zamiennik flux:heading). size: base, lg, xl; level: 1-4 (bez level to zwykły div, jak we Flux). --}}
@props(['size' => 'base', 'level' => null])

@php
    $tag = $level ? 'h'.max(1, min(4, (int) $level)) : 'div';
@endphp

<{{ $tag }} {{ $attributes->class([
    'font-display font-semibold tracking-tight text-ui-ink',
    match ($size) { 'xl' => 'text-2xl font-bold leading-tight', 'lg' => 'text-lg', default => 'text-[15px]' },
]) }}>{{ $slot }}</{{ $tag }}>
