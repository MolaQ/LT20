{{--
    Ikony 2.0 bez Flux. Własne ikony skorupy (start, results, tip, club, more, chevron) albo Heroicons
    (resources/icons/heroicons.php) pod tymi samymi nazwami co we Flux. variant: outline (domyślnie), solid, mini, micro.
--}}
@props(['name', 'variant' => 'outline'])

@php
    $custom = in_array($name, ['start', 'results', 'tip', 'club', 'more', 'chevron', 'swords', 'shield'], true);
    $solid = in_array($variant, ['solid', 'mini', 'micro'], true);
    $hero = $custom ? null : (once(fn () => require resource_path('icons/heroicons.php'))[$solid ? 'solid' : 'outline'][$name] ?? null);
    $sized = str_contains((string) $attributes->get('class'), 'size-');
    $default = $sized ? '' : match ($variant) { 'micro' => 'size-4', 'mini' => 'size-5', default => 'size-6' };
@endphp

@if ($custom)
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->class(['shrink-0', $default]) }}>
    @switch($name)
        @case('swords')
            {{-- Skrzyżowane miecze: pytanie ataku --}}
            <path d="M14.5 17.5 3 6V3h3l11.5 11.5" /><path d="m13 19 6-6" /><path d="m16 16 4 4" /><path d="m19 21 2-2" />
            <path d="M14.5 6.5 18 3h3v3l-3.5 3.5" /><path d="m5 14 4 4" /><path d="m7 17-3 3" /><path d="m3 19 2 2" />
            @break
        @case('shield')
            {{-- Tarcza: pytanie obrony --}}
            <path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z" /><path d="M12 7v10" />
            @break
        @case('start')
            <path d="M3 11 12 4l9 7" /><path d="M5 10v10h14V10" />
            @break
        @case('results')
            <path d="M6 20V10M12 20V4M18 20v-7" />
            @break
        @case('tip')
            <circle cx="12" cy="12" r="9" /><path d="m12 7 4 3-1.5 4.5h-5L8 10z" fill="currentColor" />
            @break
        @case('club')
            <path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z" />
            @break
        @case('more')
            <circle cx="5" cy="12" r="1.5" fill="currentColor" /><circle cx="12" cy="12" r="1.5" fill="currentColor" /><circle cx="19" cy="12" r="1.5" fill="currentColor" />
            @break
        @case('chevron')
            <path d="m9 6 6 6-6 6" />
            @break
    @endswitch
</svg>
@elseif ($solid)
<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon" {{ $attributes->class(['shrink-0', $default]) }}>{!! $hero !!}</svg>
@else
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" data-slot="icon" {{ $attributes->class(['shrink-0', $default]) }}>{!! $hero !!}</svg>
@endif
