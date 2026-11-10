{{-- Plakietka w kolorze (zamiennik flux:badge): color jak w Tailwind (zinc, green, red, amber, blue, lime …), size sm, icon z x-ui.icon. --}}
@props(['color' => 'zinc', 'size' => null, 'icon' => null, 'variant' => null])

@php
    $tone = match ($color) {
        'red', 'rose' => 'bg-red-500/15 text-red-700 dark:text-red-300',
        'orange' => 'bg-orange-500/15 text-orange-700 dark:text-orange-300',
        'amber', 'yellow' => 'bg-amber-400/20 text-amber-800 dark:text-amber-300',
        'lime' => 'bg-lime-500/20 text-lime-800 dark:text-lime-300',
        'green', 'emerald', 'teal' => 'bg-green-500/15 text-green-700 dark:text-green-300',
        'sky', 'cyan', 'blue', 'indigo' => 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
        'violet', 'purple', 'fuchsia', 'pink' => 'bg-purple-500/15 text-purple-700 dark:text-purple-300',
        default => 'bg-zinc-500/15 text-zinc-700 dark:text-zinc-300',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 whitespace-nowrap font-medium',
    $variant === 'pill' ? 'rounded-full' : 'rounded-md',
    $size === 'sm' ? 'px-1.5 py-0.5 text-xs' : 'px-2 py-1 text-sm',
    $tone,
]) }}>
    @if ($icon)<x-ui.icon :name="$icon" variant="micro" />@endif{{ $slot }}
</span>
