{{--
    Zakładka „Nagrody” na stronie drużyny: miejsce w wyścigu o każdą nagrodę indywidualną i króla strzelców ligi,
    z sąsiadami w tabeli (Support\AwardRace::around). Pełne tabele na /nagrody.
    Zmienne: $race (team-profile awardRace), $teamName, $mine (własna drużyna).
--}}
@if ($race === [])
    <x-ui.card>
        <x-ui.text>{{ __('The race for the awards starts after the first settled matchday of the season.') }}</x-ui.text>
    </x-ui.card>
@else
    <div class="flex flex-wrap items-center justify-between gap-2">
        <x-ui.text class="text-sm">{{ __(':team in the race for the individual awards of the season: its place and the teams around it.', ['team' => $teamName]) }}</x-ui.text>
        <a href="{{ route('awards') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-bold text-club hover:underline">
            {{ __('Full tables') }} <x-ui.icon name="arrow-right" variant="micro" />
        </a>
    </div>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($race as $key => $block)
            <a href="{{ route('awards', $key === 'mvp' ? [] : ['nagroda' => $key]) }}" wire:navigate wire:key="race-{{ $key }}"
                class="group overflow-hidden rounded-2xl border border-ui-line bg-ui-surface shadow-sm transition hover:border-club/40">
                <div class="flex items-center gap-2 bg-gradient-to-r {{ $block['grad'] }} px-4 py-2.5 text-white">
                    <x-ui.icon :name="$block['icon']" variant="mini" />
                    <span class="min-w-0 flex-1 truncate text-sm font-bold">{{ $block['label'] }}</span>
                    <span class="shrink-0 rounded-full bg-white/20 px-2 py-0.5 text-xs font-black">{{ __(':place. place', ['place' => $block['rank']]) }}</span>
                </div>
                <ol class="divide-y divide-ui-line text-sm">
                    @foreach ($block['rows'] as $row)
                        @php
                            $own = $row['rank'] === $block['rank'];
                        @endphp
                        <li class="{{ $own ? 'bg-amber-50 font-bold shadow-[inset_4px_0_0_#f59e0b] dark:bg-amber-500/10' : '' }} flex items-center gap-3 px-4 py-1.5">
                            <span class="{{ $row['rank'] <= 3 ? 'text-amber-600 dark:text-amber-300' : 'text-ui-muted' }} w-7 text-right text-xs font-black tabular-nums">{{ $row['rank'] }}.</span>
                            <span class="{{ $row['bot'] ? 'italic' : '' }} min-w-0 flex-1 truncate">{{ $row['name'] }}</span>
                            <span class="tabular-nums">{{ $row['value'] }}</span>
                        </li>
                    @endforeach
                </ol>
                @if ($block['rows'][0]['rank'] > 1)
                    <div class="border-t border-ui-line px-4 py-1.5 text-xs text-ui-muted">{{ __('Leader: :team, :value', ['team' => $block['leader']['name'], 'value' => $block['leader']['value']]) }}</div>
                @endif
            </a>
        @endforeach
    </div>
@endif
