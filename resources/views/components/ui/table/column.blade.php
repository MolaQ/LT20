{{-- Nagłówek kolumny. align: start, center, end; sortable z sorted i direction (asc/desc) pokazuje strzałkę. --}}
@props(['align' => 'start', 'sortable' => false, 'sorted' => false, 'direction' => null])

<th {{ $attributes->class([
    'whitespace-nowrap px-3 py-2.5 text-xs font-bold uppercase tracking-wide text-ui-muted first:ps-0 last:pe-0',
    match ($align) { 'center' => 'text-center', 'end' => 'text-end', default => 'text-start' },
    'cursor-pointer select-none hover:text-ui-ink' => $sortable,
]) }}>
    @if ($sortable)
        <span class="inline-flex items-center gap-1">{{ $slot }}
            @if ($sorted)<x-ui.icon :name="$direction === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" class="size-3.5" />@endif
        </span>
    @else
        {{ $slot }}
    @endif
</th>
