{{--
    Pole wyboru (zamiennik flux:checkbox). label, description. W x-ui.checkbox.group dostaje wire:model grupy
    (lista zaznaczonych wartości), poza grupą wire:model albo checked i wire:click podaje wywołujący.
--}}
@aware(['model' => null])
@props(['label' => null, 'description' => null, 'checked' => false])

<label {{ $attributes->only('class')->class('flex cursor-pointer items-start gap-2.5') }}>
    <input type="checkbox" @if ($model) wire:model="{{ $model }}" @endif @checked($checked)
        {{ $attributes->except('class')->class('mt-0.5 size-4 shrink-0 rounded border-ui-line accent-[var(--ui-club)] disabled:opacity-50') }}>
    @if ($label || $description)
        <span class="min-w-0">
            @if ($label)<span class="block text-sm font-medium">{{ $label }}</span>@endif
            @if ($description)<span class="block text-xs text-ui-muted">{{ $description }}</span>@endif
        </span>
    @endif
</label>
