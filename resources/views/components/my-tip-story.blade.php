{{--
    Grafika „Mój typ” w formacie relacji 9:16 (27.11.2026): mecz, typ gracza i zaproszenie do gry. Obrazek robi
    x-share-image (data-share-size 1080, więc wychodzi 1080 × 1920) i jest dla wszystkich, nie tylko premium:
    każde udostępnienie zaprasza znajomych gracza. Zmienne: $matchday, $tip, $sides (jak $tipSides na /tips), $team.
--}}
@props(['matchday', 'tip', 'sides', 'team' => null])

@php
    $brand = parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name');
@endphp

<div data-share-target data-share-size="1080" class="relative mx-auto flex aspect-[9/16] w-[300px] flex-col overflow-hidden rounded-3xl bg-linear-to-b from-lech-900 via-lech-800 to-lech-600 p-6 text-center text-white sm:w-[340px]">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(80%_45%_at_50%_0%,rgb(255_255_255/0.18),transparent_70%)]"></div>
    <div class="pointer-events-none absolute -bottom-24 -left-16 size-72 rounded-full bg-blue-400/20 blur-3xl"></div>

    {{-- Marka --}}
    <div class="relative flex items-center justify-center gap-2">
        <x-app-logo-icon class="size-9 shrink-0 drop-shadow" />
        <span class="text-xl font-black tracking-tight">#Lech<span class="font-light text-blue-300">TYPER</span></span>
    </div>

    <div class="relative mt-8 text-xs font-bold uppercase tracking-[0.3em] text-lech-100">{{ __('My tip') }}</div>
    <div class="relative mt-1 text-[11px] font-semibold uppercase tracking-wider text-white/70">
        {{ __('Matchday :number', ['number' => $matchday->number]) }}@if ($matchday->kickoff_at) · {{ $matchday->kickoff_at->translatedFormat('j M, H:i') }}@endif
    </div>

    {{-- Mecz i typ --}}
    <div class="relative my-auto space-y-5">
        @foreach ($sides as $side)
            @php
                $goals = $side['lech'] ? $tip->lech_goals : $tip->opponent_goals;
                $stripe = \App\Models\Rival::stripe($side['lech'] ? \App\Models\Rival::LECH_COLORS : \App\Models\Rival::colorsFor($side['name']));
            @endphp
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1 text-left">
                    @if ($stripe)
                        <span class="mb-1.5 block h-1.5 w-16 rounded-full" style="{{ $stripe }}"></span>
                    @endif
                    <span class="block truncate font-display text-2xl font-extrabold leading-tight">{{ $side['name'] }}</span>
                </div>
                <span class="grid h-20 w-16 shrink-0 place-items-center rounded-2xl bg-white text-6xl font-black tabular-nums text-lech-900 shadow-lg">{{ $goals }}</span>
            </div>
        @endforeach
    </div>

    @if (filled($team))
        <div class="relative mb-4 text-sm text-white/80">{{ __('Tipped by :team', ['team' => $team]) }}</div>
    @endif

    {{-- Zaproszenie --}}
    <div class="relative rounded-2xl bg-white/12 px-4 py-3 ring-1 ring-white/20 backdrop-blur">
        <div class="font-display text-lg font-extrabold">{{ __('Can you beat my tip?') }}</div>
        <div class="text-sm font-semibold text-amber-300">{{ $brand }}</div>
    </div>

    <x-share-image free :title="__('My tip')" class="absolute end-3 top-3 text-white" />
</div>
