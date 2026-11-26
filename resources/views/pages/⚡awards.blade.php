<?php

use App\Models\Competition;
use App\Models\Season;
use App\Support\AwardRace;
use App\Support\HallOfFame;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Wyścig o nagrody (/nagrody, 20.11.2026): klasyfikacje nagród indywidualnych bieżącego sezonu na żywo
 * (Support\AwardRace): MVP, Złota Piłka, Złoty But, Złote Rękawice i królowie strzelców lig.
 * Zalogowany widzi u góry swoje miejsca we wszystkich nagrodach, a tabela otwiera się na stronie z jego zespołem.
 * Tabela: 50 wierszy na stronę, wyszukiwarka, filtry „Moja liga” i „Bez botów”, strzałki zmiany miejsca po ostatniej kolejce.
 */
new #[Layout('layouts::public')] class extends Component {
    use WithPagination;

    /** Parametr strony w adresie (?strona=3). */
    private const PAGE = 'strona';

    private const PER_PAGE = 50;

    /** mvp, golden_ball, golden_boot, golden_gloves albo scorers (królowie strzelców lig). */
    #[Url(as: 'nagroda', except: 'mvp')]
    public string $award = 'mvp';

    /** Liga w zakładce strzelców (id rozgrywek, 0 = liga zalogowanego albo Ekstraklasa). */
    #[Url(as: 'liga', except: 0)]
    public int $league = 0;

    public string $search = '';

    /** all, league (moja liga) albo humans (bez botów). */
    public string $filter = 'all';

    public function mount(): void
    {
        if (! in_array($this->award, [...array_keys(AwardRace::AWARDS), 'scorers'], true)) {
            $this->award = 'mvp';
        }
        if (! isset($this->leagues[$this->league])) {
            $this->league = $this->defaultLeague();
        }
        if (request()->query(self::PAGE) === null) {
            $this->jumpToMe();
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('Race for the awards'));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['award', 'league', 'search', 'filter'], true)) {
            if (! in_array($this->filter, ['all', 'league', 'humans'], true)) {
                $this->filter = 'all';
            }
            if ($property === 'league' && ! isset($this->leagues[$this->league])) {
                $this->league = $this->defaultLeague();
            }
            $this->resetPage(self::PAGE);
            unset($this->ranking, $this->filtered, $this->pageItems);
        }
    }

    public function show(string $award): void
    {
        $this->award = in_array($award, [...array_keys(AwardRace::AWARDS), 'scorers'], true) ? $award : 'mvp';
        $this->jumpToMe();
    }

    /** Strona tabeli z zespołem zalogowanego gracza (czyści wyszukiwarkę i filtry). */
    public function jumpToMe(): void
    {
        $this->reset('search', 'filter');
        unset($this->ranking, $this->filtered, $this->pageItems);

        $me = $this->myTeamId();
        $index = $me ? $this->filtered->search(fn ($row) => $row['team_id'] === $me) : false;
        $this->setPage($index === false ? 1 : (int) floor($index / self::PER_PAGE) + 1, self::PAGE);
        unset($this->pageItems);
    }

    #[Computed]
    public function season(): ?Season
    {
        return AwardRace::season();
    }

    /** @return array{last: int|null, remaining: int, rows: array<int, array<string, mixed>>} */
    #[Computed]
    public function race(): array
    {
        return $this->season ? AwardRace::data($this->season) : ['last' => null, 'remaining' => 0, 'rows' => []];
    }

    /** @return array<int, array{name: string, tier: int}> */
    #[Computed]
    public function leagues(): array
    {
        return $this->season ? AwardRace::leagues($this->season) : [];
    }

    /** Miejsce zalogowanego gracza w sezonie (id season_team) albo null. */
    public function myTeamId(): ?int
    {
        $user = auth()->id();
        if (! $user) {
            return null;
        }

        foreach ($this->race['rows'] as $row) {
            if ($row['user_id'] === $user) {
                return $row['team_id'];
            }
        }

        return null;
    }

    /** Liga zalogowanego (jego rozgrywki ligowe), inaczej pierwsza z listy (Ekstraklasa). */
    private function defaultLeague(): int
    {
        $me = $this->myTeamId();
        $own = $me && $this->season ? AwardRace::leagueOf($this->season, $me) : null;

        return $own ?? (int) array_key_first($this->leagues);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function ranking(): array
    {
        if (! $this->season) {
            return [];
        }

        if ($this->award === 'scorers') {
            $competition = Competition::where('season_id', $this->season->id)->find($this->league);

            return $competition ? AwardRace::scorers($competition) : [];
        }

        return AwardRace::ranking($this->season, $this->award);
    }

    #[Computed]
    public function filtered(): Collection
    {
        $needle = mb_strtolower(trim($this->search));
        $me = $this->myTeamId();
        $tier = $me ? ($this->race['rows'][$me]['tier'] ?? null) : null;

        return collect($this->ranking)
            ->when(mb_strlen($needle) >= 2, fn ($rows) => $rows->filter(fn ($row) => str_contains(mb_strtolower($row['name']), $needle)))
            ->when($this->filter === 'humans', fn ($rows) => $rows->reject(fn ($row) => $row['bot']))
            ->when($this->filter === 'league' && $tier !== null && $this->award !== 'scorers', fn ($rows) => $rows->filter(fn ($row) => $row['tier'] === $tier))
            ->values();
    }

    #[Computed]
    public function pageItems(): LengthAwarePaginator
    {
        $current = max(1, (int) $this->getPage(self::PAGE));

        return new LengthAwarePaginator($this->filtered->forPage($current, self::PER_PAGE)->values(), $this->filtered->count(), self::PER_PAGE, $current, ['pageName' => self::PAGE]);
    }

    /**
     * Moje miejsca we wszystkich nagrodach: miejsce, wynik, zmiana i ile brakuje do celu (podium, top 10) albo przewaga lidera.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function mine(): array
    {
        $me = $this->myTeamId();
        if (! $me || ! $this->season) {
            return [];
        }

        $out = [];
        foreach (array_keys(AwardRace::AWARDS) as $award) {
            $rows = AwardRace::ranking($this->season, $award);
            $index = array_search($me, array_column($rows, 'team_id'), true);
            if ($index === false) {
                continue;
            }
            $row = $rows[$index];
            $target = match (true) {
                $row['rank'] === 1 => null,
                $row['rank'] <= 3 => 1,
                $row['rank'] <= 10 => 3,
                default => 10,
            };
            $out[$award] = $row + [
                'target' => $target,
                'gap' => $target ? max(0, $rows[$target - 1]['value'] - $row['value']) : (isset($rows[1]) ? $row['value'] - $rows[1]['value'] : 0),
                'total' => count($rows),
            ];
        }

        return $out;
    }

    /** Podium każdej nagrody. @return array<string, list<array<string, mixed>>> */
    #[Computed]
    public function podiums(): array
    {
        if (! $this->season) {
            return [];
        }

        return collect(AwardRace::AWARDS)->keys()
            ->mapWithKeys(fn ($award) => [$award => array_values(array_filter(array_slice(AwardRace::ranking($this->season, $award), 0, 3), fn ($row) => $row['value'] > 0))])
            ->all();
    }

    #[Computed]
    public function icons(): array
    {
        return HallOfFame::iconUrls();
    }
}; ?>

@php
    $meta = \App\Support\AwardRace::meta();
    $tabs = $meta + ['scorers' => ['label' => __('Top scorers'), 'icon' => 'flag', 'grad' => 'from-sky-500 to-blue-700', 'rule' => __('Most goals in the league table. On a tie the higher place wins.')]];
    $current = $tabs[$award];
    $myTeam = $this->myTeamId();
    $race = $this->race;
    $leader = $this->ranking[0]['value'] ?? 0;
    $tierTone = fn (int $tier) => match (true) {
        $tier === 1 => 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200',
        $tier <= 4 => 'bg-sky-100 text-sky-900 dark:bg-sky-500/20 dark:text-sky-200',
        $tier <= 10 => 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-300',
        default => 'bg-lime-100 text-lime-900 dark:bg-lime-500/20 dark:text-lime-200',
    };
    $leagueName = fn (int $tier) => \App\Enums\League::tryFrom($tier)?->label() ?? '';
    $myRow = $myTeam ? collect($this->ranking)->firstWhere('team_id', $myTeam) : null;
    $myOnPage = $myRow && $this->pageItems->contains('team_id', $myTeam);
@endphp

<div class="flex w-full flex-col gap-6">
    <x-page-banner :eyebrow="$this->season ? ($race['last'] ? __(':season · after matchday :number', ['season' => $this->season->title, 'number' => $race['last']]) : $this->season->title) : __('Individual awards')"
        :title="__('Race for the awards')"
        :subtitle="__('Who will win the Season MVP, the Golden Ball, the Golden Boot and the Golden Gloves? The tables are updated after every settled matchday. The awards go to the trophy cabinet and give Hall of Fame points.')">
        <x-slot:aside>
            <div class="grid grid-cols-2 gap-2 text-center">
                <div class="rounded-xl bg-white/10 px-4 py-2">
                    <div class="text-2xl font-black tabular-nums">{{ $race['remaining'] }}</div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider opacity-75">{{ __('Matchdays left') }}</div>
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-2">
                    <div class="text-2xl font-black tabular-nums">{{ count($race['rows']) }}</div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider opacity-75">{{ __('Teams in the race') }}</div>
                </div>
            </div>
        </x-slot:aside>
    </x-page-banner>

    @if (! $race['last'])
        <x-ui.card>
            <x-ui.text>{{ __('The race starts after the first settled matchday of the season.') }}</x-ui.text>
        </x-ui.card>
    @else
        {{-- Moje miejsca: bez szukania i przewijania --}}
        @if ($this->mine !== [])
            <section data-share-target class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 dark:border-amber-400/40 dark:bg-amber-500/10">
                <div class="mb-3 flex items-center gap-2 text-sm font-bold text-amber-900 dark:text-amber-200">
                    <x-ui.icon name="user" variant="mini" class="size-5" />
                    <span class="min-w-0 flex-1">{{ __('Your places: :team', ['team' => $race['rows'][$myTeam]['name']]) }}</span>
                    <x-share-image :title="__('My places in the race for the awards')" />
                </div>
                <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                    @foreach ($this->mine as $key => $row)
                        <button type="button" wire:click="show('{{ $key }}')" wire:key="mine-{{ $key }}"
                            class="rounded-xl bg-ui-surface p-3 text-left shadow-sm ring-1 ring-amber-200 transition hover:ring-amber-400 dark:ring-amber-400/20">
                            <span class="flex items-center gap-2">
                                <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-gradient-to-br {{ $meta[$key]['grad'] }} text-white"><x-ui.icon :name="$meta[$key]['icon']" variant="micro" /></span>
                                <span class="truncate text-xs font-semibold text-ui-muted">{{ $meta[$key]['label'] }}</span>
                            </span>
                            <span class="mt-2 flex items-baseline gap-2">
                                <span class="text-2xl font-black tabular-nums text-ui-ink">{{ $row['rank'] }}.</span>
                                <span class="text-xs text-ui-muted">{{ __('of :count', ['count' => $row['total']]) }}</span>
                                @include('partials.award-move', ['move' => $row['move'], 'class' => 'ms-auto'])
                            </span>
                            <span class="mt-1 block text-xs text-ui-muted">
                                {{ trans_choice(':count pt|:count pts', $row['value'], ['count' => $row['value']]) }} ·
                                <span class="font-semibold text-ui-ink">
                                    @if ($row['target'] === null)
                                        {{ __('leader, :gap ahead', ['gap' => $row['gap']]) }}
                                    @elseif ($row['target'] === 1)
                                        {{ __(':gap to the lead', ['gap' => $row['gap']]) }}
                                    @elseif ($row['target'] === 3)
                                        {{ __(':gap to the podium', ['gap' => $row['gap']]) }}
                                    @else
                                        {{ __(':gap to the top 10', ['gap' => $row['gap']]) }}
                                    @endif
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Podium nagród (z ikonami pucharów z panelu, bez nich z domyślnym pucharem), z przyciskiem obrazka na sociale --}}
        <section data-share-target class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-bold uppercase tracking-wider text-ui-muted">{{ __('Race leaders') }}</h2>
            <x-share-image :title="__('Race for the awards')" class="text-ui-muted" />
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this->podiums as $key => $podium)
                <button type="button" wire:click="show('{{ $key }}')" wire:key="podium-{{ $key }}"
                    class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $meta[$key]['grad'] }} p-4 text-left text-white shadow-md transition hover:-translate-y-0.5">
                    {{-- Nagłówek: opis z lewej, puchar z prawej we własnej kolumnie, żeby nie nachodził na tekst (26.11.2026) --}}
                    <span class="relative flex items-start gap-2">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] font-semibold uppercase tracking-wider text-white/85">{{ $meta[$key]['label'] }}</span>
                            <span class="block text-[11px] text-white/75">{{ $meta[$key]['short'] }}</span>
                        </span>
                        @if (isset($this->icons[$key]))
                            <img src="{{ $this->icons[$key] }}" alt="" class="pointer-events-none -mt-1 size-14 shrink-0 object-contain drop-shadow-lg">
                        @else
                            <x-ui.icon name="trophy" class="pointer-events-none -mt-1 size-12 shrink-0 text-white/25" />
                        @endif
                    </span>
                    <span class="relative mt-3 block space-y-1.5">
                        @forelse ($podium as $i => $row)
                            <span class="{{ $i === 0 ? 'text-base font-bold' : 'text-sm opacity-90' }} flex items-center gap-2">
                                <span class="{{ $i === 0 ? 'bg-white text-zinc-900' : 'bg-white/20' }} grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-black">{{ $row['rank'] }}</span>
                                <span class="{{ $row['bot'] ? 'italic' : '' }} min-w-0 flex-1 truncate">{{ $row['name'] }}</span>
                                <span class="font-black tabular-nums">{{ $row['value'] }}</span>
                            </span>
                        @empty
                            <span class="block text-sm text-white/80">{{ __('No points yet.') }}</span>
                        @endforelse
                    </span>
                </button>
            @endforeach
        </div>
        </section>

        {{-- Zakładki nagród --}}
        <div class="flex gap-1 overflow-x-auto rounded-2xl bg-ui-raised p-1" role="tablist">
            @foreach ($tabs as $key => $tab)
                <button type="button" role="tab" wire:click="show('{{ $key }}')" aria-selected="{{ $award === $key ? 'true' : 'false' }}"
                    @class(['inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition', 'bg-ui-surface shadow' => $award === $key, 'text-ui-muted hover:text-ui-ink' => $award !== $key])>
                    <x-ui.icon :name="$tab['icon']" variant="micro" /> {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        <x-lech-panel :title="$current['label']" :subtitle="$current['rule']" :icon="$current['icon']" flush>
            <div class="flex flex-wrap items-center gap-3 border-b border-ui-line p-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable :placeholder="__('Search team (min. 2 letters)')" />
                </div>
                @if ($award === 'scorers')
                    <div class="w-full sm:w-56">
                        <x-ui.select wire:model.live="league">
                            @foreach ($this->leagues as $id => $item)
                                <x-ui.select.option :value="$id">{{ $item['name'] }}</x-ui.select.option>
                            @endforeach
                        </x-ui.select>
                    </div>
                @endif
                <div class="flex gap-1 rounded-full bg-ui-raised p-1 text-xs font-bold">
                    @foreach (array_filter(['all' => __('Everyone'), 'league' => ($myTeam && $award !== 'scorers') ? __('My league') : null, 'humans' => __('Without bots')]) as $value => $label)
                        <button type="button" wire:click="$set('filter', '{{ $value }}')"
                            class="{{ $filter === $value ? 'bg-club text-club-ink shadow' : 'text-ui-muted hover:text-ui-ink' }} rounded-full px-3 py-1 transition">{{ $label }}</button>
                    @endforeach
                </div>
                @if ($myRow)
                    <x-ui.btn size="sm" icon="map-pin" wire:click="jumpToMe">{{ __('My place') }}</x-ui.btn>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-[11px] font-bold uppercase tracking-wider text-ui-muted dark:bg-white/5">
                        <tr>
                            <th class="w-14 py-2 ps-4 text-left">#</th>
                            @if ($award !== 'scorers')
                                <th class="w-10 text-center" title="{{ __('Change after the last matchday') }}">±</th>
                            @endif
                            <th class="px-2 text-left">{{ __('Team') }}</th>
                            @if ($award === 'mvp')
                                <th class="hidden px-2 text-right sm:table-cell" title="{{ __('Correct outcomes in league matches') }}">{{ __('Outc.') }}</th>
                                <th class="hidden px-2 text-right sm:table-cell" title="{{ __('Attack points') }}">{{ __('Att.') }}</th>
                                <th class="hidden px-2 text-right sm:table-cell" title="{{ __('Defence points') }}">{{ __('Def.') }}</th>
                            @elseif ($award === 'scorers')
                                <th class="hidden px-2 text-right sm:table-cell" title="{{ __('Place in the league table') }}">{{ __('Place') }}</th>
                                <th class="hidden px-2 text-right sm:table-cell">{{ __('Matches') }}</th>
                            @endif
                            <th class="px-2 text-right text-club">{{ $current['unit'] ?? __('Goals') }}</th>
                            <th class="pe-4 ps-2 text-right" title="{{ __('Gap to the leader') }}">{{ __('Gap') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ui-line">
                        @forelse ($this->pageItems as $row)
                            @php
                                $mine = $myTeam && $row['team_id'] === $myTeam;
                            @endphp
                            <tr wire:key="race-{{ $award }}-{{ $row['team_id'] }}" class="{{ $mine ? 'bg-amber-50 shadow-[inset_4px_0_0_#f59e0b] dark:bg-amber-500/10' : '' }}" @if ($mine) id="moje-miejsce" @endif>
                                <td class="py-2 ps-4">
                                    <span class="{{ $mine ? 'bg-amber-400 text-zinc-900' : ($row['rank'] <= 3 ? 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200' : 'bg-ui-raised text-ui-muted') }} grid size-7 place-items-center rounded-full text-[11px] font-black tabular-nums">{{ $row['rank'] }}</span>
                                </td>
                                @if ($award !== 'scorers')
                                    <td class="text-center">@include('partials.award-move', ['move' => $row['move'], 'class' => ''])</td>
                                @endif
                                <td class="px-2 py-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($row['url'])
                                            <a href="{{ $row['url'] }}" wire:navigate class="{{ $row['bot'] ? 'italic' : '' }} font-semibold text-ui-ink hover:text-club hover:underline">{{ $row['name'] }}</a>
                                        @else
                                            <span class="{{ $row['bot'] ? 'italic' : '' }} font-semibold">{{ $row['name'] }}</span>
                                        @endif
                                        @if ($mine)
                                            <span class="rounded bg-amber-400 px-1.5 py-0.5 text-[10px] font-black uppercase text-zinc-900">{{ __('You') }}</span>
                                        @endif
                                        @if ($row['bot'])
                                            <span class="rounded bg-ui-raised px-1.5 py-0.5 text-[10px] font-bold uppercase text-ui-muted">{{ __('Bot') }}</span>
                                        @endif
                                    </div>
                                    <span class="{{ $tierTone($row['tier']) }} mt-0.5 inline-block rounded px-1.5 text-[10px] font-semibold">{{ $leagueName($row['tier']) }}</span>
                                </td>
                                @if ($award === 'mvp')
                                    <td class="hidden px-2 text-right tabular-nums sm:table-cell">{{ $row['outcomes'] }}</td>
                                    <td class="hidden px-2 text-right tabular-nums sm:table-cell">{{ $row['attack'] }}</td>
                                    <td class="hidden px-2 text-right tabular-nums sm:table-cell">{{ $row['defense'] }}</td>
                                @elseif ($award === 'scorers')
                                    <td class="hidden px-2 text-right tabular-nums sm:table-cell">{{ $row['place'] }}.</td>
                                    <td class="hidden px-2 text-right tabular-nums sm:table-cell">{{ $row['played'] }}</td>
                                @endif
                                <td class="px-2 text-right font-black tabular-nums text-club">{{ $row['value'] }}</td>
                                <td class="pe-4 ps-2 text-right tabular-nums text-ui-muted">{{ $leader - $row['value'] > 0 ? '−' . ($leader - $row['value']) : '–' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-ui-muted">{{ __('No teams match the filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-lech-panel>

        {{ $this->pageItems->links() }}

        <x-ui.text class="text-xs">{{ __('During the season a tie is decided by the higher league and then the higher place on the list. After the season, as in the rules, more trophies of the season decide first, then the higher place in the league table.') }}</x-ui.text>

        {{-- Pasek z moim miejscem, gdy mój wiersz nie jest na tej stronie tabeli --}}
        @if ($myRow && ! $myOnPage)
            <div class="sticky bottom-3 z-20 mx-auto flex w-full max-w-xl items-center gap-3 rounded-2xl bg-zinc-900 px-4 py-2.5 text-white shadow-xl ring-1 ring-white/10">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-amber-400 text-xs font-black text-zinc-900">{{ $myRow['rank'] }}</span>
                <span class="min-w-0 flex-1 text-sm">
                    <span class="block truncate font-bold">{{ $myRow['name'] }} · {{ $myRow['value'] }}</span>
                    <span class="block text-xs text-white/70">{{ $current['label'] }}</span>
                </span>
                <button type="button" wire:click="jumpToMe" class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20">{{ __('Show') }}</button>
            </div>
        @endif
    @endif
</div>
