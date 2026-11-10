{{-- Przełącznik kilku opcji (zakładki, filtry). options: [wartość => etykieta]. Wiązanie przez wire:model albo x-model. --}}
@props(['options' => []])

<div x-data="{ value: null }" x-modelable="value" {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
    {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->class('flex gap-1 rounded-2xl bg-ui-raised p-1') }} role="radiogroup">
    @foreach ($options as $value => $label)
        <button type="button" role="radio" :aria-checked="value === @js($value)" @click="value = @js($value); window.ui.vibrate()"
            class="flex-1 rounded-xl py-2.5 text-sm font-bold transition"
            :class="value === @js($value) ? 'bg-ui-surface shadow' : 'text-ui-muted'">{{ $label }}</button>
    @endforeach
</div>
