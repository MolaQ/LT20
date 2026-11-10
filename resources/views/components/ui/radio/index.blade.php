{{-- Opcja w x-ui.radio.group. --}}
@props(['value', 'label' => null])

<button type="button" role="radio" :aria-checked="value === @js($value)" @click="value = @js($value)"
    {{ $attributes->class('flex-1 rounded-xl px-3 py-2 text-sm font-bold transition') }}
    :class="value === @js($value) ? 'bg-ui-surface shadow' : 'text-ui-muted'">{{ $label ?? $slot }}</button>
