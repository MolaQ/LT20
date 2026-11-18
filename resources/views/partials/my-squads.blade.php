{{--
    „Twoje składy w tej kolejce” (prawa część karty kolejki na stronie głównej, App\Support\MySquads): kafelek na rozgrywki
    z nagłówkiem w ich barwach i licznikiem, wiersze zawodników równej szerokości (numer, inicjał i nazwisko, wychowanek,
    płomień formy, doświadczenie w klubie gracza). Parametry: $squads, $tipUrl (wybór składu na /tips).
--}}
@php
    $fmtExp = fn ($v) => \App\Support\ClubStats::experience($v);
@endphp
<div data-share-target class="flex flex-col gap-3 bg-ui-surface px-4 py-4 sm:px-5">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-widest text-club">{{ __('Your squads in this matchday') }}</p>
            <p class="text-xs text-ui-muted">{{ __('Flame: player form. Last number: experience in your club.') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ $tipUrl }}" wire:navigate data-share-hide class="inline-flex items-center gap-1 text-sm font-semibold text-club hover:underline">
                {{ __('Change squads') }} <x-ui.icon name="arrow-right" variant="micro" />
            </a>
            <x-share-image :title="__('Your squads in this matchday')" class="text-ui-muted" />
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($squads as $squad)
            <div wire:key="squad-{{ $squad['set'] }}" class="overflow-hidden rounded-xl border border-ui-line bg-ui-raised/40">
                <div class="comp-banner flex items-center justify-between gap-2 !rounded-none !border-x-0 !border-t-0 px-3 py-2 !shadow-none" style="{{ \App\Support\CompetitionColors::style($squad['key']) }}">
                    <span class="relative truncate text-sm font-bold">{{ $squad['name'] }}</span>
                    <span class="relative shrink-0 rounded-full bg-white/20 px-2 text-[11px] font-bold tabular-nums">{{ count($squad['players']) }}/{{ $squad['max'] }}</span>
                </div>
                @if ($squad['players'] === [])
                    <a href="{{ $tipUrl }}" wire:navigate class="m-2 flex items-center justify-center gap-1 rounded-lg border-[1.5px] border-dashed border-ui-line px-3 py-3 text-sm font-semibold text-club hover:bg-ui-raised">
                        <x-ui.icon name="plus" variant="micro" /> {{ __('Pick players') }}
                    </a>
                @else
                    <ul class="divide-y divide-ui-line px-1.5 py-1">
                        @foreach ($squad['players'] as $player)
                            <li class="grid grid-cols-[1.75rem_minmax(0,1fr)_2.75rem_2.25rem] items-center gap-2 px-1.5 py-1.5 text-sm">
                                <span class="rounded-md border border-ui-line bg-ui-surface py-0.5 text-center text-[11px] font-bold tabular-nums text-ui-muted">{{ $player['number'] ?? '–' }}</span>
                                <span class="flex min-w-0 items-center gap-1 font-semibold">
                                    <span class="truncate">{{ $player['name'] }}</span>
                                    @if ($player['academy'])
                                        <x-ui.icon name="academic-cap" variant="micro" class="size-3.5 shrink-0 text-club" title="{{ __('Academy player') }}" />
                                    @endif
                                </span>
                                <span class="flex items-center justify-end gap-0.5 text-[11px] font-bold tabular-nums text-amber-600 dark:text-amber-400" title="{{ __('Form: experience from the last :count Lech matches', ['count' => \App\Support\MatchReport::FORM_MATCHES]) }}">
                                    <x-ui.icon name="fire" variant="micro" class="size-3" />{{ $fmtExp($player['form']) }}
                                </span>
                                <span class="text-right text-[11px] tabular-nums text-ui-muted" title="{{ __('Experience in your club') }}">{{ $fmtExp($player['experience']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
