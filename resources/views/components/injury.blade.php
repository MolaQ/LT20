{{-- Czerwony krzyż na białym tle: zawodnik niedostępny (odpoczywa w klubie) w liście „Odpoczywający”. --}}
@props(['title' => null])
<svg viewBox="0 0 16 16" aria-hidden="{{ $title ? 'false' : 'true' }}" @if ($title) role="img" aria-label="{{ $title }}" @endif {{ $attributes->class('size-3.5 shrink-0') }}>
    @if ($title)<title>{{ $title }}</title>@endif
    <rect x="0.5" y="0.5" width="15" height="15" rx="3" fill="#fff" stroke="#e4e4e7" />
    <path d="M6.3 3h3.4v3.3H13v3.4H9.7V13H6.3V9.7H3V6.3h3.3z" fill="#dc2626" />
</svg>
