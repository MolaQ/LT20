{{-- Ikony 2.0 (własne SVG, bez Flux). --}}
@props(['name'])

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
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
