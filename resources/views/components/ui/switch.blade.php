{{-- Przełącznik wł./wył. (zamiennik flux:switch). label, description; wire:model trafia do ukrytego checkboxa. --}}
@props(['label' => null, 'description' => null])

<label {{ $attributes->only('class')->class('flex cursor-pointer items-center justify-between gap-4') }}>
    @if ($label || $description)
        <span class="min-w-0">
            @if ($label)<span class="block text-sm font-semibold">{{ $label }}</span>@endif
            @if ($description)<span class="block text-xs text-ui-muted">{{ $description }}</span>@endif
        </span>
    @endif
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" {{ $attributes->except('class')->class('peer sr-only') }}>
        <span class="h-6 w-11 rounded-full bg-ui-line transition peer-checked:bg-club peer-focus-visible:ring-2 peer-focus-visible:ring-club/40 peer-disabled:opacity-50"></span>
        <span class="absolute start-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
    </span>
</label>
