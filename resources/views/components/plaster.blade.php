{{-- Plaster: zmęczony zawodnik (koniec regeneracji blisko), może zagrać, ale regeneracja po meczu trwa dłużej. Zastępuje znacznik „×2”. --}}
@props(['title' => null])
<svg viewBox="0 0 20 20" aria-hidden="{{ $title ? 'false' : 'true' }}" @if ($title) role="img" aria-label="{{ $title }}" @endif {{ $attributes->class('size-4 shrink-0') }}>
    @if ($title)<title>{{ $title }}</title>@endif
    <g transform="rotate(-45 10 10)">
        <rect x="1" y="6.5" width="18" height="7" rx="3.5" fill="#f5c89a" stroke="#b7794a" stroke-width="0.8" />
        <rect x="6.5" y="6.9" width="7" height="6.2" rx="1" fill="#fbe3c8" />
        <circle cx="8.4" cy="8.9" r="0.55" fill="#c58b5c" />
        <circle cx="11.6" cy="8.9" r="0.55" fill="#c58b5c" />
        <circle cx="8.4" cy="11.1" r="0.55" fill="#c58b5c" />
        <circle cx="11.6" cy="11.1" r="0.55" fill="#c58b5c" />
    </g>
</svg>
