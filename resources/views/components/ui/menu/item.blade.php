{{-- Pozycja menu (zamiennik flux:menu.item). icon, variant danger; z href jest linkiem. --}}
@props(['icon' => null, 'variant' => null, 'href' => null])

@php
    $classes = [
        'flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-start text-sm font-medium transition',
        $variant === 'danger' ? 'text-bad hover:bg-bad-soft' : 'text-ui-ink hover:bg-ui-raised',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="size-4 opacity-70" />@endif{{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->except('as')->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="size-4 opacity-70" />@endif{{ $slot }}
    </button>
@endif
