{{--
    Pole tekstowe (zamiennik flux:input). label, icon (z lewej), suffix; wire:model i pozostałe atrybuty trafiają do <input>,
    class do opakowania. viewable = przycisk z okiem, który pokazuje wpisane hasło.
    Na tej przebudowie pola wpisywania są wyjątkiem (wyszukiwarka, kwoty, logowanie), resztę się klika.
--}}
@props(['label' => null, 'icon' => null, 'suffix' => null, 'size' => null, 'viewable' => false])

@php
    // Błąd walidacji: z wire:model albo, w zwykłym formularzu, z name.
    $model = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $type = $attributes->get('type', 'text');
@endphp

<label {{ $attributes->only('class')->class('flex flex-col gap-1') }} @if ($viewable) x-data="{ visible: false }" @endif>
    @if ($label)<span class="text-sm font-semibold">{{ $label }}</span>@endif
    <span class="relative flex items-center">
        @if ($icon)<x-ui.icon :name="$icon" variant="mini" class="pointer-events-none absolute start-3 size-4 text-ui-muted" />@endif
        <input {{ $attributes->except(['class', 'type'])->class([
            'w-full rounded-xl border border-ui-line bg-ui-surface text-ui-ink placeholder:text-ui-muted focus:border-club focus:outline-none focus:ring-2 focus:ring-club/30 disabled:opacity-60',
            $size === 'sm' ? 'h-8 text-sm' : 'h-11 text-base',
            $icon ? 'ps-9' : 'ps-3',
            ($suffix || $viewable) ? 'pe-12' : 'pe-3',
        ]) }} type="{{ $type }}" @if ($viewable) :type="visible ? 'text' : @js($type)" @endif>
        @if ($suffix)<span class="pointer-events-none absolute end-3 text-sm text-ui-muted">{{ $suffix }}</span>@endif
        @if ($viewable)
            <button type="button" @click="visible = !visible" class="absolute end-1.5 grid size-8 place-items-center rounded-lg text-ui-muted hover:text-ui-ink"
                :aria-label="visible ? @js(__('Hide password')) : @js(__('Show password'))">
                <x-ui.icon name="eye" variant="mini" class="size-4" x-show="!visible" />
                <x-ui.icon name="eye-slash" variant="mini" class="size-4" x-show="visible" x-cloak />
            </button>
        @endif
    </span>
    @if ($model)
        @error($model)<span class="text-xs text-bad">{{ $message }}</span>@enderror
    @endif
</label>
