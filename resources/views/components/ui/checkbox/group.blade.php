{{-- Grupa pól wyboru z jednym wire:model (tablica wartości), zamiennik flux:checkbox.group: model="selected". --}}
@props(['model' => null, 'label' => null])

<div {{ $attributes->class('flex flex-col gap-2') }} role="group">
    @if ($label)<span class="text-sm font-semibold">{{ $label }}</span>@endif
    {{ $slot }}
</div>
