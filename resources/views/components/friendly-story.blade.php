{{--
    Grafika wyniku sparingu w formacie relacji 9:16 (28.11.2026): obie drużyny z bramkami, wynik słownie i zaproszenie
    do rewanżu. Obrazek robi x-share-image (data-share-size 1080, więc 1080 × 1920), dla wszystkich, nie tylko premium:
    każde udostępnienie zaprasza do gry. Zmienne: $friendly (rozliczony App\Models\Friendly), $userId (czyj punkt widzenia).
--}}
@props(['friendly', 'userId'])

@php
    $brand = parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name');
    [$mine, $theirs] = $friendly->scoreFor($userId) ?? [0, 0];
    $me = $friendly->challenger_id === $userId ? $friendly->challenger : $friendly->opponent;
    $rival = $friendly->rivalOf($userId);
    $verdict = $mine > $theirs ? __('Win!') : ($mine < $theirs ? __('Defeat') : __('It is a draw'));
    $rows = [[\App\Support\Friendlies::teamName($me), $mine, true], [\App\Support\Friendlies::teamName($rival), $theirs, false]];
@endphp

<div data-share-target data-share-size="1080" class="relative mx-auto flex aspect-[9/16] w-[300px] flex-col overflow-hidden rounded-3xl bg-linear-to-b from-slate-900 via-slate-800 to-lech-700 p-6 text-center text-white sm:w-[340px]">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(80%_45%_at_50%_0%,rgb(255_255_255/0.16),transparent_70%)]"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-16 size-72 rounded-full bg-amber-400/15 blur-3xl"></div>

    {{-- Marka --}}
    <div class="relative flex items-center justify-center gap-2">
        <x-app-logo-icon class="size-9 shrink-0 drop-shadow" />
        <span class="text-xl font-black tracking-tight">#Lech<span class="font-light text-blue-300">TYPER</span></span>
    </div>

    <div class="relative mt-8 text-xs font-bold uppercase tracking-[0.3em] text-amber-300">{{ __('Friendly') }}</div>
    <div class="relative mt-1 text-[11px] font-semibold uppercase tracking-wider text-white/70">
        {{ __('Matchday :number', ['number' => $friendly->matchday?->number]) }}@if ($friendly->matchday?->kickoff_at) · {{ $friendly->matchday->kickoff_at->translatedFormat('j M') }}@endif
    </div>

    {{-- Wynik --}}
    <div class="relative my-auto space-y-5">
        @foreach ($rows as [$name, $goals, $isMe])
            <div class="flex items-center gap-3">
                <span @class(['block min-w-0 flex-1 truncate text-left font-display text-2xl font-extrabold leading-tight', 'text-white/80' => ! $isMe])>{{ $name }}</span>
                <span @class(['grid h-20 w-16 shrink-0 place-items-center rounded-2xl text-6xl font-black tabular-nums shadow-lg', 'bg-white text-slate-900' => $isMe, 'bg-white/15 text-white ring-1 ring-white/25' => ! $isMe])>{{ $goals }}</span>
            </div>
        @endforeach
        <div class="font-display text-3xl font-black uppercase tracking-tight text-amber-300">{{ $verdict }}</div>
    </div>

    {{-- Zaproszenie do rewanżu --}}
    <div class="relative rounded-2xl bg-white/12 px-4 py-3 ring-1 ring-white/20 backdrop-blur">
        <div class="font-display text-lg font-extrabold">{{ __('Fancy a rematch?') }}</div>
        <div class="text-sm font-semibold text-amber-300">{{ $brand }}</div>
    </div>

    <x-share-image free :title="__('Friendly')" class="absolute end-3 top-3 text-white" />
</div>
