{{-- Kółko z inicjałami (zamiennik flux:avatar). name albo initials; size: xs, sm, base, lg. --}}
@props(['name' => '', 'initials' => null, 'size' => 'base'])

@php
    $letters = $initials ?? collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

<span {{ $attributes->class([
    'inline-grid shrink-0 place-items-center rounded-full bg-club font-bold text-club-ink',
    match ($size) { 'xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'lg' => 'size-12 text-base', default => 'size-9 text-sm' },
]) }} title="{{ $name }}">{{ $letters }}</span>
