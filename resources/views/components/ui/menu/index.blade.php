{{-- Lista w x-ui.dropdown (zamiennik flux:menu); strona i kierunek z dropdown (align, position). --}}
@aware(['align' => 'start', 'position' => 'bottom'])

<div x-show="open" x-cloak x-transition.opacity {{ $attributes->class([
    'absolute z-30 min-w-48 rounded-xl border border-ui-line bg-ui-surface p-1 shadow-lg',
    $align === 'end' ? 'end-0' : 'start-0',
    $position === 'top' ? 'bottom-full mb-1' : 'top-full mt-1',
]) }}>
    {{ $slot }}
</div>
