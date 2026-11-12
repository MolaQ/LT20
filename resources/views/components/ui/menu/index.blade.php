{{-- Lista w x-ui.dropdown (zamiennik flux:menu); strona i kierunek z dropdown (align, position), pozycja fixed z dropdown (pos). --}}
@aware(['align' => 'start', 'position' => 'bottom'])

<div x-show="open" x-cloak x-transition.opacity
    :style="{
        {{ $position === 'top' ? 'bottom' : 'top' }}: ({{ $position === 'top' ? 'pos.bottom' : 'pos.top' }}) + 'px',
        {{ $align === 'end' ? 'right' : 'left' }}: ({{ $align === 'end' ? 'pos.right' : 'pos.left' }}) + 'px',
    }"
    {{ $attributes->class('fixed z-50 min-w-48 rounded-xl border border-ui-line bg-ui-surface p-1 shadow-lg') }}>
    {{ $slot }}
</div>
