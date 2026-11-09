{{--
    Tekst pomocniczy (zamiennik flux:text). size: sm, lg; variant: strong (mocniejszy, w kolorze tekstu).
    Rozmiar i kolor z class wygrywają z domyślnymi (domyślnych wtedy nie dodajemy).
--}}
@props(['size' => null, 'variant' => null])

@php
    $own = (string) $attributes->get('class');
    $ownSize = preg_match('/(^|\s)text-(xs|sm|base|lg|xl|\[\d)/', $own) === 1;
    $ownColor = preg_match('/(^|\s)(dark:)?text-(?!(xs|sm|base|lg|xl|2xl|3xl|center|left|right|start|end|balance|pretty|wrap|nowrap|ellipsis|clip|\[\d)\b)[a-z]/', $own) === 1;
@endphp

<div {{ $attributes->class([
    $ownSize ? '' : match ($size) { 'sm' => 'text-xs', 'lg' => 'text-base', default => 'text-sm' },
    $ownColor ? '' : ($variant === 'strong' ? 'font-medium text-ui-ink' : 'text-ui-muted'),
]) }}>{{ $slot }}</div>
