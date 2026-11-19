@props(['competition'])
{{--
    Blok partnera rozgrywek w barwach tych rozgrywek: napis (np. „Partner rozgrywek”), nazwa rozgrywek, duże logo,
    nazwa partnera; cały blok jest linkiem do strony partnera przez licznik kliknięć. Prawy panel wyników (równa wysokość z banerem rozgrywek) i telefon (nad banerem).
--}}
@php
    $sponsor = $competition->sponsor;
    $colorKey = $competition->trophyKey();
    $dashed = \App\Support\CompetitionColors::dashed($colorKey) ? 'comp-dashed' : '';
@endphp
<a @if ($sponsor->url) href="{{ route('sponsor.click', $sponsor) }}" target="_blank" rel="noopener sponsored" @endif
    {{ $attributes->class(['comp-banner group flex flex-col justify-center gap-2.5 rounded-2xl px-4 py-3 transition', $dashed, 'hover:brightness-110' => (bool) $sponsor->url]) }}
    style="{{ \App\Support\CompetitionColors::style($colorKey) }}">
    <span class="relative z-10 flex items-center justify-between gap-2 text-[11px] font-semibold uppercase tracking-widest">
        <span class="comp-accent">{{ $sponsor->label ?: __('Competition partner') }}</span>
        <span class="truncate opacity-80">{{ $competition->name ?: $competition->type->label() }}</span>
    </span>
    <span class="relative z-10 flex items-center gap-4">
        {{-- Logo tak, jak je skadrowano (np. w kole z przezroczystym tłem), bez białej podkładki; podkładka tylko pod literą --}}
        @if ($sponsor->logoUrl())
            <img src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}" class="size-16 shrink-0 object-contain drop-shadow-[0_10px_16px_rgb(0_0_0/0.65)]">
        @else
            <span class="comp-partner-logo flex size-16 shrink-0 items-center justify-center rounded-xl text-2xl font-black uppercase">{{ mb_substr($sponsor->name, 0, 1) }}</span>
        @endif
        <span class="min-w-0">
            <span class="line-clamp-2 text-lg font-bold leading-tight">{{ $sponsor->name }}</span>
        </span>
    </span>
</a>
