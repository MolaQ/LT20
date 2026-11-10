{{-- Przyciski sklejone w jeden pasek (zamiennik flux:button.group). --}}
<div {{ $attributes->class('inline-flex items-stretch [&>*:not(:first-child)]:rounded-s-none [&>*:not(:last-child)]:rounded-e-none [&>*:not(:first-child)]:-ms-px') }}>{{ $slot }}</div>
