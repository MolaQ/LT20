{{--
    Pole tekstowe (zamiennik flux:input). label, icon (z lewej), suffix; wire:model i pozostałe atrybuty trafiają do <input>,
    class do opakowania. Na tej przebudowie pola wpisywania są wyjątkiem (wyszukiwarka, kwoty), resztę się klika.
--}}
@props(['label' => null, 'icon' => null, 'suffix' => null, 'size' => null])

<label {{ $attributes->only('class')->class('flex flex-col gap-1') }}>
    @if ($label)<span class="text-sm font-semibold">{{ $label }}</span>@endif
    <span class="relative flex items-center">
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="pointer-events-none absolute start-3 size-4 text-ui-muted" />@endif
        <input {{ $attributes->except('class')->merge(['type' => 'text'])->class([
            'w-full rounded-xl border border-ui-line bg-ui-surface text-ui-ink placeholder:text-ui-muted focus:border-club focus:outline-none focus:ring-2 focus:ring-club/30',
            $size === 'sm' ? 'h-8 text-sm' : 'h-10 text-base',
            $icon ? 'ps-9' : 'ps-3',
            $suffix ? 'pe-12' : 'pe-3',
        ]) }}>
        @if ($suffix)<span class="pointer-events-none absolute end-3 text-sm text-ui-muted">{{ $suffix }}</span>@endif
    </span>
    @if ($attributes->whereStartsWith('wire:model')->first())
        @error($attributes->whereStartsWith('wire:model')->first())<span class="text-xs text-bad">{{ $message }}</span>@enderror
    @endif
</label>
