{{-- Komórka. align: start, center, end; variant strong = pogrubiony tekst w kolorze treści. --}}
@props(['align' => 'start', 'variant' => null])

<td {{ $attributes->class([
    'px-3 py-2.5 align-middle first:ps-0 last:pe-0',
    match ($align) { 'center' => 'text-center', 'end' => 'text-end', default => 'text-start' },
    $variant === 'strong' ? 'font-semibold text-ui-ink' : '',
]) }}>{{ $slot }}</td>
