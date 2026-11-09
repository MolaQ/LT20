{{-- Lista wyboru (zamiennik flux:select): zwykły <select> z opcjami x-ui.select.option. wire:model trafia do <select>, class do opakowania. --}}
@props(['label' => null, 'placeholder' => null])

<label {{ $attributes->only('class')->class('relative flex flex-col gap-1') }}>
    @if ($label)<span class="text-sm font-semibold">{{ $label }}</span>@endif
    <span class="relative flex items-center">
        <select {{ $attributes->except('class')->class('h-10 w-full appearance-none rounded-xl border border-ui-line bg-ui-surface pe-9 ps-3 text-sm font-medium text-ui-ink focus:border-club focus:outline-none focus:ring-2 focus:ring-club/30') }}>
            @if ($placeholder)<option value="" disabled>{{ $placeholder }}</option>@endif
            {{ $slot }}
        </select>
        <x-ui.icon name="chevron-up-down" variant="mini" class="pointer-events-none absolute end-3 size-4 text-ui-muted" />
    </span>
</label>
