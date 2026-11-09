{{--
    Wybór jednej z opcji (zamiennik flux:radio.group, wariant segmented): przyciski x-ui.radio w pasku.
    wire:model (albo x-model) trafia do grupy, label nad paskiem.
--}}
@props(['label' => null, 'variant' => 'segmented'])

<div {{ $attributes->only('class')->class('flex flex-col gap-1') }}>
    @if ($label)<span class="text-sm font-semibold">{{ $label }}</span>@endif
    <div x-data="{ value: null }" x-modelable="value" {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
        class="flex gap-1 rounded-2xl bg-ui-raised p-1" role="radiogroup">
        {{ $slot }}
    </div>
</div>
