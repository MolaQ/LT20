{{-- Ramka z informacją (zamiennik flux:callout). variant: secondary (domyślnie), success, warning, danger; icon z x-ui.icon; heading opcjonalny. --}}
@props(['variant' => 'secondary', 'icon' => null, 'heading' => null, 'color' => null])

@php
    $tone = match ($variant) {
        'success' => 'border-green-500/30 bg-good-soft',
        'warning' => 'border-amber-500/30 bg-gold-soft',
        'danger' => 'border-red-500/30 bg-bad-soft',
        default => 'border-ui-line bg-ui-raised',
    };
@endphp

<div {{ $attributes->class(['flex gap-3 rounded-2xl border p-3.5 text-sm', $tone]) }} role="status">
    @if ($icon)<x-ui.icon :name="$icon" class="mt-0.5 size-5 text-ui-muted" />@endif
    <div class="min-w-0 flex-1 space-y-1">
        @if ($heading)<p class="font-semibold">{{ $heading }}</p>@endif
        {{ $slot }}
    </div>
</div>
