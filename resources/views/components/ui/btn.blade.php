{{--
    Zwykły przycisk (zamiennik flux:button, w odróżnieniu od x-ui.button nie zajmuje całej szerokości).
    variant: outline (domyślnie), primary, filled, ghost, subtle, danger; size: base, sm, xs; icon i icon-trailing z x-ui.icon.
    Z href jest linkiem (wire:navigate dodaje wywołujący, tak jak we Flux).
--}}
@props(['variant' => 'outline', 'size' => 'base', 'icon' => null, 'iconTrailing' => null, 'href' => null, 'square' => false])

@php
    $iconOnly = $slot->isEmpty() || $square;
    $classes = [
        'inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl font-semibold transition active:scale-[.97] disabled:pointer-events-none disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-club',
        match ($size) {
            'xs' => $iconOnly ? 'size-6 text-xs' : 'h-6 px-2 text-xs',
            'sm' => $iconOnly ? 'size-8 text-sm' : 'h-8 px-3 text-sm',
            default => $iconOnly ? 'size-10 text-sm' : 'h-10 px-4 text-sm',
        },
        match ($variant) {
            'primary' => 'bg-club text-club-ink hover:brightness-110',
            'filled' => 'bg-ui-raised text-ui-ink hover:brightness-95',
            'danger' => 'bg-bad text-white hover:brightness-110',
            'ghost' => 'text-ui-ink hover:bg-ui-raised',
            'subtle' => 'text-ui-muted hover:bg-ui-raised hover:text-ui-ink',
            default => 'border border-ui-line bg-ui-surface text-ui-ink hover:bg-ui-raised',
        },
    ];
    $iconSize = $size === 'base' ? 'size-5' : 'size-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" :variant="$size === 'base' ? 'outline' : 'mini'" :class="$iconSize" />@endif{{ $slot }}@if ($iconTrailing)<x-ui.icon :name="$iconTrailing" variant="mini" class="size-4" />@endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" :variant="$size === 'base' ? 'outline' : 'mini'" :class="$iconSize" />@endif{{ $slot }}@if ($iconTrailing)<x-ui.icon :name="$iconTrailing" variant="mini" class="size-4" />@endif
    </button>
@endif
