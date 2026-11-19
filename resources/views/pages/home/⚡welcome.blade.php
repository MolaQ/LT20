<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\MatchEvent;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Tip;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Strona główna z banerem sezonu (etap 13): duża cyfra rzymska, hasło sezonu i sponsor („powered by”,
 * logo z linkiem do strony sponsora), a pod spodem najbliższa kolejka z odliczaniem do pierwszego gwizdka.
 * Bez aktywnego sezonu: „Sezon wkrótce”, jeśli kolejny sezon jest już w przygotowaniu, w przeciwnym razie nic.
 */
new #[Layout('layouts::public')] class extends Component {
    public function render(): View
    {
        return $this->view()->title(config('app.name'));
    }

    #[Computed]
    public function season(): ?Season
    {
        return Season::current();
    }

    /** Sezon w przygotowaniu (szkic albo zatwierdzony), pokazywany jako „wkrótce”, gdy nie ma aktywnego. */
    #[Computed]
    public function upcoming(): ?Season
    {
        return $this->season
            ? null
            : Season::whereIn('status', [SeasonStatus::Draft->value, SeasonStatus::Approved->value])->orderBy('number')->first();
    }

    /** Najbliższa kolejka bez wyniku (z rywalem i datą), a gdy wszystkie rozegrane, to ostatnia. */
    #[Computed]
    public function matchday(): ?Matchday
    {
        if (!$this->season) {
            return null;
        }

        $matchdays = Matchday::where('season_id', $this->season->id)->orderBy('number')->get();

        return $matchdays->first(fn($m) => $m->status !== MatchdayStatus::Played && $m->isFilled())
            ?? $matchdays->last(fn($m) => $m->status === MatchdayStatus::Played);
    }

    /** Ostatnia rozegrana kolejka, gdy w tablicy jest już następna (wynik i protokół pod spodem). */
    #[Computed]
    public function lastPlayed(): ?Matchday
    {
        if (!$this->matchday || $this->matchday->status === MatchdayStatus::Played) {
            return null;
        }

        return Matchday::where('season_id', $this->matchday->season_id)->where('status', MatchdayStatus::Played->value)->orderByDesc('number')->first();
    }

    /**
     * Protokół meczu Lecha (gole, kartki, zmiany zawodników Lecha) rozegranej kolejki.
     *
     * @return \Illuminate\Support\Collection<int, MatchEvent>
     */
    public function protocolOf(?Matchday $matchday)
    {
        if (!$matchday || $matchday->status !== MatchdayStatus::Played || !$matchday->lech_match_id) {
            return collect();
        }

        return MatchEvent::with(['player:id,name', 'other:id,name'])->where('lech_match_id', $matchday->lech_match_id)->orderBy('id')->get();
    }

    /** Liczby serwisu (na dole strony): gracze, oddane typy, rozegrane pojedynki, zdobyte trofea. */
    #[Computed]
    public function numbers(): array
    {
        return \App\Support\HomeStats::numbers();
    }

    /** Ciekawostki sezonu (celność typów, najskuteczniejsi) albo null przed pierwszą rozegraną kolejką. */
    #[Computed]
    public function seasonStats(): ?array
    {
        return $this->season ? \App\Support\HomeStats::season($this->season) : null;
    }

    /** Ostatni mistrzowie najważniejszych rozgrywek (App\Support\CompetitionHistory). */
    #[Computed]
    public function champions(): array
    {
        return \App\Support\CompetitionHistory::latestChampions();
    }

    /**
     * Stan typu zalogowanego gracza w tej kolejce: odpowiedzi na pytania jego zestawów i wystawieni zawodnicy (wszystkie typy rozgrywek).
     *
     * @return array{answered: int, questions: int, players: int}|null
     */
    #[Computed]
    public function myTipStatus(): ?array
    {
        if (!$this->matchday || !auth()->check()) {
            return null;
        }

        // Pytania z zestawów rozgrywek, w których gracz jeszcze gra (każdy typ rozgrywek ma osobny zestaw).
        $sets = collect(\App\Support\PlayerCompetitions::types(auth()->id(), $this->matchday->season_id, $this->matchday->number))->map(fn ($set) => $set->value)->all();
        $questionIds = \App\Models\MatchdayQuestion::where('matchday_id', $this->matchday->id)->whereIn('score_set', $sets)->pluck('id');

        return [
            'answered' => $questionIds->isEmpty() ? 0 : \App\Models\QuestionAnswer::whereIn('matchday_question_id', $questionIds)->where('user_id', auth()->id())->count(),
            'questions' => $questionIds->count(),
            'players' => \App\Models\DesignatedPlayer::where('matchday_id', $this->matchday->id)->where('user_id', auth()->id())->count(),
        ];
    }

    /**
     * Liga gracza w bieżącym sezonie: pięć miejsc wokół gracza (cała tabela pod linkiem). Gość albo gracz
     * bez ligi widzi czołówkę Ekstraklasy. Przy każdym miejscu strefa (awans, spadek), zmiana miejsca po ostatniej
     * kolejce i forma z pięciu ostatnich meczów; pod tabelą najbliższa kolejka z rywalem gracza (18.11.2026).
     *
     * @return array{name: string, key: string, url: string, round: int, mine: ?array<string, mixed>, rows: array<int, array<string, mixed>>, rival: ?array{name: string, url: ?string, bot: bool}}|null
     */
    #[Computed]
    public function myLeague(): ?array
    {
        if (!$this->season) {
            return null;
        }

        $team = auth()->check() ? \App\Models\SeasonTeam::where('season_id', $this->season->id)->where('user_id', auth()->id())->first() : null;
        $entry = $team ? \App\Models\CompetitionEntry::with('competition')->where('season_team_id', $team->id)
            ->whereHas('competition', fn($q) => $q->where('season_id', $this->season->id)->whereIn('type', [\App\Enums\CompetitionType::League->value, \App\Enums\CompetitionType::Swiss->value]))
            ->first() : null;

        $competition = $entry?->competition ?? \App\Models\Competition::where('season_id', $this->season->id)
            ->where('type', \App\Enums\CompetitionType::League->value)->where('tier', \App\Enums\League::Ekstraklasa->value)->first();

        if (!$competition) {
            return null;
        }

        // Rozegrane mecze ligi od najnowszych: ostatnia kolejka z wynikiem, forma i zmiana miejsc.
        $played = \App\Models\Fixture::where('competition_id', $competition->id)->whereNotNull('home_goals')
            ->orderByDesc('round')->toBase()->get(['round', 'home_entry_id', 'away_entry_id', 'home_goals', 'away_goals']);
        $round = (int) ($played->first()->round ?? 0);
        $table = \App\Support\Standings::for($competition)->values();
        $before = $round >= 2 ? \App\Support\Standings::for($competition, null, $round - 1)->pluck('entry_id')->flip() : collect();

        $rows = $table->map(fn($row, $index) => [
            'place' => $index + 1,
            'entry' => $row['entry_id'],
            'team' => $row['team'],
            'name' => $row['team']?->name ?? '—',
            'bot' => (bool) $row['team']?->is_bot,
            'goals' => $row['for'] . ':' . $row['against'],
            'points' => $row['points'],
            'zone' => \App\Support\Standings::zone($competition, $index + 1, $table->count()),
            'move' => $before->has($row['entry_id']) ? (int) $before[$row['entry_id']] - $index : 0,
            'me' => $entry !== null && $row['entry_id'] === $entry->id,
        ]);

        $mine = $rows->search(fn($row) => $row['me']);
        $start = $mine === false ? 0 : max(0, min($mine - 2, $rows->count() - 5));
        $shown = $rows->slice($start, 5)->values()->map(function (array $row) use ($played): array {
            // Forma: pięć ostatnich meczów od najstarszego, W/D/L.
            $row['form'] = $played->filter(fn($f) => (int) $f->home_entry_id === $row['entry'] || (int) $f->away_entry_id === $row['entry'])
                ->take(5)->reverse()->map(function ($f) use ($row): string {
                    [$for, $against] = (int) $f->home_entry_id === $row['entry'] ? [$f->home_goals, $f->away_goals] : [$f->away_goals, $f->home_goals];

                    return $for > $against ? 'W' : ($for == $against ? 'D' : 'L');
                })->values()->all();

            return $row;
        });

        // Rywal gracza w najbliższej kolejce bez wyniku.
        $rival = null;
        if ($entry && $this->matchday && $this->matchday->status !== MatchdayStatus::Played) {
            $fixture = \App\Models\Fixture::where('competition_id', $competition->id)->where('round', $this->matchday->number)
                ->where(fn($q) => $q->where('home_entry_id', $entry->id)->orWhere('away_entry_id', $entry->id))->first();
            $otherId = $fixture ? ((int) $fixture->home_entry_id === $entry->id ? $fixture->away_entry_id : $fixture->home_entry_id) : null;
            $other = $otherId ? \App\Models\CompetitionEntry::with('seasonTeam.user:id,name,team_name', 'seasonTeam.bot:id,name')->find($otherId)?->seasonTeam : null;
            if ($other) {
                $rival = [
                    'name' => $other->name,
                    'url' => $other->user ? route('team.show', $other->user) : ($other->bot ? route('bot.show', $other->bot) : null),
                    'bot' => $other->is_bot,
                ];
            }
        }

        return [
            'name' => $competition->name ?: $competition->type->label(),
            'key' => $competition->trophyKey(),
            'url' => route('results', ['season_slug' => 'sezon-' . $this->season->number, 'competition_slug' => $competition->slug()]) . ($team ? '?team=' . $team->id : ''),
            'round' => $round,
            'mine' => $mine === false ? null : $rows[$mine],
            'rows' => $shown->all(),
            'rival' => $rival,
        ];
    }

    /**
     * Składy zalogowanego gracza w najbliższej kolejce (prawa część karty kolejki), pusta lista dla gościa i po rozliczeniu.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function mySquads(): array
    {
        return auth()->check() && $this->matchday && $this->matchday->status !== MatchdayStatus::Played
            ? \App\Support\MySquads::for((int) auth()->id(), $this->matchday)
            : [];
    }

    /** Typ zalogowanego gracza na tę kolejkę (null, gdy brak albo gość). */
    #[Computed]
    public function myTip(): ?Tip
    {
        return $this->matchday && auth()->check()
            ? Tip::where('matchday_id', $this->matchday->id)->where('user_id', auth()->id())->first()
            : null;
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    @if ($this->season)
        @php
            $season = $this->season;
        @endphp

        {{-- Baner sezonu (wzór dla banerów innych stron: x-page-banner) --}}
        <x-page-banner :title="$season->title" :subtitle="filled($season->slogan) ? $season->slogan : null">
            <x-slot:aside>
                @if ($season->sponsor_logo_url || filled($season->sponsor_name))
                    @php
                        $sponsorTag = filled($season->sponsor_url) ? 'a' : 'div';
                    @endphp
                    <{{ $sponsorTag }}
                        @if (filled($season->sponsor_url)) href="{{ $season->sponsor_url }}" target="_blank" rel="noopener sponsored" @endif
                        class="flex shrink-0 items-center gap-3 self-start rounded-xl bg-white/10 px-4 py-3 backdrop-blur transition hover:bg-white/20 md:self-auto">
                        <span class="text-xs uppercase tracking-widest text-lech-200">powered by</span>
                        @if ($season->sponsor_logo_url)
                            <img src="{{ $season->sponsor_logo_url }}" alt="{{ $season->sponsor_name ?? '' }}"
                                class="h-12 w-12 rounded-md bg-white object-contain p-1">
                        @endif
                        @if (filled($season->sponsor_name))
                            <span class="font-semibold">{{ $season->sponsor_name }}</span>
                        @endif
                    </{{ $sponsorTag }}>
                @endif
            </x-slot:aside>
        </x-page-banner>

        {{-- Ostrzeżenie „kartki” po kolejkach bez typu (App\Support\Inactivity). --}}
        @auth
            <x-inactivity-warning />
        @endauth

        {{-- Najbliższa kolejka (albo ostatnia, gdy wszystkie rozegrane): tablica wyników z odliczaniem do pierwszego gwizdka --}}
        @if ($this->matchday)
            @php
                $matchday = $this->matchday;
                $played = $matchday->status === \App\Enums\MatchdayStatus::Played;
                $open = !$played && $matchday->isOpenForTips() && $matchday->kickoff_at;
                // Gospodarz po lewej jak w prawdziwym meczu, Lech zawsze wyróżniony.
                $sides = $matchday->is_home
                    ? [['name' => 'Lech Poznań', 'lech' => true, 'goals' => $matchday->lech_goals], ['name' => $matchday->opponent, 'lech' => false, 'goals' => $matchday->opponent_goals]]
                    : [['name' => $matchday->opponent, 'lech' => false, 'goals' => $matchday->opponent_goals], ['name' => 'Lech Poznań', 'lech' => true, 'goals' => $matchday->lech_goals]];
                $lechWon = $played && $matchday->lech_goals > $matchday->opponent_goals;
                $lechLost = $played && $matchday->lech_goals < $matchday->opponent_goals;
            @endphp
            <section data-share-target class="lech-bar-shadow overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
                {{-- Belka: rodzaj kolejki, numer i rozgrywki, stan --}}
                <div class="lech-bar flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                    <div class="flex items-center gap-2">
                        <x-ui.icon :name="$played ? 'flag' : 'clock'" variant="mini" class="text-lech-200" />
                        <span class="text-xs font-semibold uppercase tracking-wider">{{ $played ? __('Last matchday') : __('Next matchday') }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs [&>span]:whitespace-nowrap">
                        <span class="rounded-full bg-white/15 px-2.5 py-1 font-semibold">{{ __('Matchday :number', ['number' => $matchday->number]) }}</span>
                        @if ($matchday->competitionLabel())
                            <span class="rounded-full bg-white/10 px-2.5 py-1 text-lech-100">{{ $matchday->competitionLabel() }}</span>
                        @endif
                        <span class="{{ $played ? 'bg-zinc-200 text-zinc-800' : ($open ? 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200') }} rounded-full px-2.5 py-1 font-bold uppercase">
                            {{ $played ? __('Full time') : ($open ? __('Tipping open') : __('Tipping closed')) }}
                        </span>
                        <x-share-image :title="__('Matchday :number', ['number' => $matchday->number])" class="text-white" />
                    </div>
                </div>

                @php
                    $squads = $this->mySquads;
                @endphp
                <div @class(['grid', 'lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]' => $squads !== []])>
                {{-- Lewa część: mecz na niebieskim tle Lecha --}}
                {{-- Kolumna na całą wysokość karty: tablica i odliczanie na środku, stan typu przy dolnej krawędzi --}}
                <div class="relative flex flex-col justify-between gap-6 overflow-hidden bg-linear-to-br from-lech-900 via-lech-800 to-lech-600 px-5 py-6 text-white sm:px-8 sm:py-8">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(90%_70%_at_100%_0%,rgb(255_255_255/0.12),transparent_60%)]"></div>
                    {{-- Tablica: gospodarz (zawsze z lewej, więc bez plakietek dom/wyjazd), wynik albo „vs”, gość --}}
                    <div class="relative my-auto flex flex-col gap-6">
                    <div class="relative grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-8">
                        @foreach ($sides as $index => $side)
                            @if ($index === 1)
                                <div class="flex flex-col items-center gap-1">
                                    @if ($played)
                                        <div class="{{ $lechWon ? 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200' : ($lechLost ? 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200') }} flex items-center gap-2 rounded-xl px-4 py-1.5 font-display text-3xl font-bold tabular-nums sm:text-4xl">
                                            <span>{{ $sides[0]['goals'] }}</span><span class="opacity-60">:</span><span>{{ $sides[1]['goals'] }}</span>
                                        </div>
                                    @else
                                        <div class="grid size-12 place-items-center rounded-full bg-white/15 text-sm font-black uppercase tracking-widest ring-1 ring-white/25 sm:size-14 sm:text-base">vs</div>
                                    @endif
                                </div>
                            @endif
                            <div class="{{ $index === 0 ? 'items-end text-right' : 'items-start text-left' }} flex min-w-0 flex-col gap-1">
                                {{-- Barwy klubu (rywal z bazy w panelu Rywale, Lech na stałe) --}}
                                @if ($stripe = \App\Models\Rival::stripe($side['lech'] ? \App\Models\Rival::LECH_COLORS : \App\Models\Rival::colorsFor($side['name'])))
                                    <span class="mb-1 h-2 w-20 rounded-full shadow-sm ring-1 ring-black/10 sm:w-32 dark:ring-white/10" style="{{ $stripe }}"></span>
                                @endif
                                <span class="{{ $side['lech'] ? 'drop-shadow-[0_0_14px_rgb(96_165_250/0.8)]' : '' }} font-display text-xl font-bold leading-tight text-white sm:text-3xl xl:text-4xl">{{ $side['name'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Protokół meczu (tylko zawodnicy Lecha) --}}
                    @php
                        $protocol = $this->protocolOf($played ? $matchday : null);
                    @endphp
                    @if ($protocol->isNotEmpty())
                        <div class="relative mx-auto w-full max-w-md rounded-xl bg-white px-3 py-1 text-zinc-900">
                            @include('partials.match-protocol', ['events' => $protocol])
                        </div>
                    @endif

                    @if ($matchday->kickoff_at)
                        <div class="relative flex items-center justify-center gap-1.5 text-sm text-lech-100">
                            <x-ui.icon name="calendar-days" variant="mini" />
                            <span>{{ $matchday->kickoff_at->translatedFormat('l, j F Y, H:i') }}</span>
                        </div>
                    @endif

                    {{-- Odliczanie do pierwszego gwizdka (koniec typowania): kafelki dni, godzin, minut, sekund --}}
                    @if ($open)
                        <div x-data="{
                                end: {{ $matchday->kickoff_at->getTimestamp() * 1000 }},
                                parts: [0, 0, 0, 0],
                                tick() {
                                    let s = Math.max(0, Math.floor((this.end - Date.now()) / 1000));
                                    this.parts = [Math.floor(s / 86400), Math.floor(s % 86400 / 3600), Math.floor(s % 3600 / 60), s % 60];
                                },
                            }" x-init="tick(); setInterval(() => tick(), 1000)" class="relative space-y-2 text-center">
                            <div class="text-xs font-bold uppercase tracking-widest text-lech-100">{{ __('Tipping closes in') }}</div>
                            {{-- Tablica klapkowa jak na dworcu: każda cyfra na osobnej klapce (uiFlap), przy zmianie się przewraca. --}}
                            @php
                                $dayDigits = max(2, strlen((string) intdiv(max(0, $matchday->kickoff_at->getTimestamp() - now()->getTimestamp()), 86400)));
                            @endphp
                            <div class="flex flex-wrap justify-center gap-3 sm:gap-5">
                                @foreach ([__('days'), __('hours'), __('min'), __('sec')] as $unit)
                                    @php
                                        $part = $loop->index;
                                        $width = $part === 0 ? $dayDigits : 2;
                                    @endphp
                                    <div class="flex flex-col items-center gap-1.5">
                                        <div class="flex gap-[3px] text-4xl sm:text-5xl" role="timer" aria-label="{{ $unit }}">
                                            @for ($d = 0; $d < $width; $d++)
                                                <span class="flap" x-data="uiFlap('0')" x-effect="set(String(parts[{{ $part }}]).padStart({{ $width }}, '0').slice(-{{ $width }})[{{ $d }}])">
                                                    <span class="flap-half flap-top"><span x-text="cur"></span></span>
                                                    <span class="flap-half flap-bottom"><span x-text="flipping ? prev : cur"></span></span>
                                                    <template x-if="flipping">
                                                        <span>
                                                            <span class="flap-half flap-top flap-leaf-top"><span x-text="prev"></span></span>
                                                            <span class="flap-half flap-bottom flap-leaf-bottom" @animationend="flipping = false"><span x-text="cur"></span></span>
                                                        </span>
                                                    </template>
                                                </span>
                                            @endfor
                                        </div>
                                        <div class="text-[11px] font-medium uppercase tracking-wider text-lech-200">{{ $unit }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    </div>

                    {{-- Akcje: typowanie albo wyniki --}}
                    <div class="relative flex flex-wrap items-center justify-center gap-2 border-t border-white/15 pt-5">
                        @if ($played)
                            <x-ui.btn :href="route('results')" wire:navigate icon="trophy">{{ __('Results and tables') }}</x-ui.btn>
                        @elseif (!auth()->check())
                            <x-ui.btn variant="primary" :href="route('login')" wire:navigate>{{ __('Log in to tip') }}</x-ui.btn>
                        @elseif ($matchday->isOpenForTips())
                            {{-- Stan typu: wynik, pytania i zawodnicy jako równe kafelki, przycisk tej samej wysokości obok --}}
                            @php
                                $status = $this->myTipStatus;
                                $tipUrl = route('tips', ['matchday_slug' => 'kolejka-' . $matchday->number]);
                                $tiles = [
                                    [__('Score'), $this->myTip?->score() ?? '–', $this->myTip ? 'done' : 'missing'],
                                ];
                                if ($status && $status['questions'] > 0) {
                                    $tiles[] = [__('Questions'), $status['answered'] . '/' . $status['questions'], $status['answered'] >= $status['questions'] ? 'done' : ($status['answered'] > 0 ? 'partial' : 'missing')];
                                }
                                $tiles[] = [__('Lech players picked'), (string) ($status['players'] ?? 0), ($status['players'] ?? 0) > 0 ? 'done' : 'missing'];
                            @endphp
                            {{-- Jeden styl kafelków (szkło na niebieskim tle), stan typu kolorem paska i ikony: zielony gotowe, bursztynowy niepełne, czerwony brak --}}
                            {{-- Telefon: kafelki w jednym rzędzie (po trzy albo dwa), przycisk pod nimi na całą szerokość --}}
                            <div @class(['grid w-full gap-2 sm:gap-3', 'grid-cols-3 sm:grid-cols-4' => count($tiles) === 3, 'grid-cols-2 sm:grid-cols-3' => count($tiles) === 2])>
                                @foreach ($tiles as [$label, $value, $state])
                                    <a href="{{ $tipUrl }}" wire:navigate @class([
                                        'group relative flex min-h-20 flex-col justify-between gap-2 overflow-hidden rounded-xl border border-white/15 bg-white/10 px-2.5 py-3 text-left shadow-sm backdrop-blur-sm transition hover:-translate-y-0.5 hover:bg-white/15 hover:shadow-md sm:min-h-24 sm:gap-3 sm:px-4 sm:py-3.5',
                                    ])>
                                        <span @class([
                                            'absolute inset-x-0 top-0 h-1',
                                            'bg-green-400' => $state === 'done',
                                            'bg-amber-400' => $state === 'partial',
                                            'bg-red-400' => $state === 'missing',
                                        ])></span>
                                        <span class="flex items-center justify-between gap-1 text-[10px] font-bold uppercase tracking-wide text-lech-100 sm:gap-2 sm:text-[11px] sm:tracking-wider">
                                            {{ $label }}
                                            <x-ui.icon :name="$state === 'done' ? 'check-circle' : ($state === 'partial' ? 'exclamation-circle' : 'plus-circle')" variant="mini"
                                                :class="$state === 'done' ? 'text-green-400' : ($state === 'partial' ? 'text-amber-400' : 'text-red-400')" />
                                        </span>
                                        <span class="font-display text-2xl font-bold leading-none tabular-nums text-white sm:text-3xl">{{ $value }}</span>
                                    </a>
                                @endforeach
                                <a href="{{ $tipUrl }}" wire:navigate
                                    class="col-span-full flex min-h-12 items-center justify-center gap-2 rounded-xl bg-lech-500 px-4 py-3 font-bold text-white shadow-lg shadow-lech-950/40 ring-1 ring-white/25 transition hover:-translate-y-0.5 hover:bg-lech-400 sm:col-span-1 sm:min-h-24 sm:flex-col sm:gap-1.5">
                                    <x-ui.icon name="pencil-square" class="size-6" />
                                    <span>{{ $this->myTip ? __('Change tip') : __('Tip now') }}</span>
                                </a>
                            </div>
                        @else
                            @if ($this->myTip)
                                <x-ui.badge color="zinc">{{ __('Your tip: :score', ['score' => $this->myTip->score()]) }}</x-ui.badge>
                            @endif
                            <x-ui.btn :href="route('results')" wire:navigate icon="trophy">{{ __('Results and tables') }}</x-ui.btn>
                        @endif
                    </div>
                </div>

                {{-- Prawa część: składy gracza w tej kolejce (tylko zalogowany z rozgrywkami, przed rozliczeniem) --}}
                @if ($squads !== [])
                    @include('partials.my-squads', ['squads' => $squads, 'tipUrl' => route('tips', ['matchday_slug' => 'kolejka-' . $matchday->number])])
                @endif
                </div>
            </section>

            {{-- Puls kolejki na żywo: ilu graczy już typuje, składy, pytania, pojedynki --}}
            @unless ($played)
                <livewire:pages::home.live-pulse :matchday-id="$matchday->id" :key="'live-' . $matchday->id" />
            @endunless

            {{-- Rywale w kolejce: na komputerze są w prawej kolumnie, na telefonie i tablecie tutaj, zamiast w menu „Więcej” --}}
            @auth
                <div class="xl:hidden">
                    <livewire:pages::home.rivals-panel />
                </div>
            @endauth

            {{-- Ostatni mecz Lecha (gdy w tablicy jest już następna kolejka): wynik i protokół --}}
            @if ($last = $this->lastPlayed)
                @php
                    $lastProtocol = $this->protocolOf($last);
                    $lastScore = $last->is_home ? $last->lech_goals . ':' . $last->opponent_goals : $last->opponent_goals . ':' . $last->lech_goals;
                @endphp
                <section data-share-target class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ui-line px-5 py-3">
                        <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Last matchday') }} · {{ __('Matchday :number', ['number' => $last->number]) }}
                            <x-share-image :title="__('Last matchday').' '.$last->number" class="normal-case tracking-normal" /></span>
                        <span class="text-sm font-bold">
                            {{ $last->is_home ? 'Lech Poznań' : $last->opponent }}
                            <span class="lech-bar mx-1.5 rounded-md px-2 py-0.5 tabular-nums">{{ $lastScore }}</span>
                            {{ $last->is_home ? $last->opponent : 'Lech Poznań' }}
                        </span>
                    </div>
                    @if ($lastProtocol->isNotEmpty())
                        <div class="mx-auto max-w-md px-5 py-2">
                            @include('partials.match-protocol', ['events' => $lastProtocol])
                        </div>
                    @endif
                </section>
            @endif
        @endif

        {{-- Liga gracza (gość: czołówka Ekstraklasy) obok ciekawostek sezonu albo skrótu zasad przed pierwszą kolejką --}}
        <div class="grid gap-6 lg:grid-cols-2">
            @if ($league = $this->myLeague)
                @php
                    $nextMatchday = $this->matchday && $this->matchday->status !== MatchdayStatus::Played ? $this->matchday : null;
                    $formTones = ['W' => 'bg-green-600', 'D' => 'bg-zinc-400 dark:bg-zinc-500', 'L' => 'bg-red-600'];
                @endphp
                <section data-share-target class="flex flex-col overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
                    {{-- Nagłówek w barwach rozgrywek --}}
                    <div class="comp-banner flex items-center justify-between gap-3 !rounded-none !border-x-0 !border-t-0 px-5 py-4 !shadow-none" style="{{ \App\Support\CompetitionColors::style($league['key']) }}">
                        <div class="relative min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80">
                                {{ $league['mine'] ? __('Your league') : __('The top of Ekstraklasa') }} &middot;
                                {{ $league['round'] > 0 ? trans_choice('After :count matchday|After :count matchdays', $league['round'], ['count' => $league['round']]) : __('Before the start') }}
                            </p>
                            <h2 class="truncate font-display text-xl font-bold">{{ $league['name'] }}</h2>
                        </div>
                        <div class="relative flex items-center gap-3">
                            @if ($league['mine'])
                                <div class="text-right">
                                    <div class="font-display text-3xl font-bold leading-none tabular-nums">{{ $league['mine']['place'] }}.</div>
                                    <div class="text-[11px] font-semibold uppercase tracking-wide opacity-80">{{ __('place') }}</div>
                                </div>
                            @endif
                            <x-share-image :title="$league['name']" />
                        </div>
                    </div>

                    {{-- Nagłówki kolumn: forma tylko od pierwszej kolejki z wynikiem i na szerszym ekranie --}}
                    <div class="flex items-center gap-2 px-6 pb-1 pt-3 text-[10px] font-bold uppercase tracking-wider text-ui-muted">
                        <span class="w-7 text-center">#</span>
                        <span class="flex-1 ps-7">{{ __('Team') }}</span>
                        @if ($league['round'] > 0)
                            <span class="hidden w-[7.5rem] sm:block">{{ __('Form') }}</span>
                        @endif
                        <span class="w-12 text-right">{{ __('Goals') }}</span>
                        <span class="w-9 text-right">{{ __('Pts') }}</span>
                    </div>
                    <ol class="space-y-1 px-3 pb-3">
                        @foreach ($league['rows'] as $row)
                            <li wire:key="league-row-{{ $row['place'] }}" @class([
                                'relative flex items-center gap-2 rounded-xl py-2 pe-3 ps-4 text-sm',
                                'bg-club/10 ring-1 ring-inset ring-club' => $row['me'],
                                'odd:bg-ui-raised/40' => !$row['me'],
                            ])>
                                {{-- Strefa awansu albo spadku --}}
                                @if ($row['zone'])
                                    <span class="absolute inset-y-2 start-1 w-[3px] rounded-full {{ $row['zone'] === 'up' ? 'bg-green-500' : 'bg-red-500' }}" title="{{ $row['zone'] === 'up' ? __('Promotion') : __('Relegation') }}"></span>
                                @endif
                                <span @class([
                                    'grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums',
                                    'bg-amber-400 text-amber-950' => $row['place'] === 1,
                                    'bg-ui-raised' => $row['place'] !== 1,
                                ])>{{ $row['place'] }}</span>
                                {{-- Zmiana miejsca po ostatniej kolejce --}}
                                <span class="w-6 shrink-0 text-center text-[10px] font-bold tabular-nums">
                                    @if ($row['move'] > 0)
                                        <span class="text-green-600 dark:text-green-400" title="{{ __('Up :count places', ['count' => $row['move']]) }}">&#9650;{{ $row['move'] }}</span>
                                    @elseif ($row['move'] < 0)
                                        <span class="text-red-600 dark:text-red-400" title="{{ __('Down :count places', ['count' => -$row['move']]) }}">&#9660;{{ -$row['move'] }}</span>
                                    @else
                                        <span class="text-ui-muted">&middot;</span>
                                    @endif
                                </span>
                                <span @class(['min-w-0 flex-1 truncate font-semibold', 'italic' => $row['bot']])><x-supporter-name :user-id="$row['team']?->user_id">{{ $row['name'] }}</x-supporter-name></span>
                                @if ($row['team'])
                                    <x-champion-trophies :team="$row['team']" />
                                @endif
                                @if ($league['round'] > 0)
                                    <span class="hidden w-[7.5rem] shrink-0 gap-0.5 sm:flex">
                                        @foreach ($row['form'] as $result)
                                            <span class="grid size-5 place-items-center rounded text-[10px] font-bold text-white {{ $formTones[$result] }}">{{ __($result) }}</span>
                                        @endforeach
                                    </span>
                                @endif
                                <span class="w-12 shrink-0 text-right text-xs tabular-nums text-ui-muted">{{ $row['goals'] }}</span>
                                <span class="w-9 shrink-0 text-right font-display text-base font-bold tabular-nums">{{ $row['points'] }}</span>
                            </li>
                        @endforeach
                    </ol>

                    {{-- Najbliższa kolejka (typowanie dla zalogowanych) i rywal gracza --}}
                    @if ($nextMatchday)
                        <div class="mx-4 mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-xl bg-ui-raised/60 px-3 py-2 text-xs text-ui-muted">
                            <a href="{{ auth()->check() ? route('tips', ['matchday_slug' => 'kolejka-' . $nextMatchday->number]) : route('login') }}" wire:navigate class="inline-flex items-center gap-1 font-semibold text-club hover:underline">
                                <x-ui.icon name="clock" variant="micro" class="size-3.5" />
                                {{ __('Matchday :number', ['number' => $nextMatchday->number]) }}:
                                {{ $nextMatchday->is_home ? 'Lech' : $nextMatchday->opponent }} &ndash; {{ $nextMatchday->is_home ? $nextMatchday->opponent : 'Lech' }}@if ($nextMatchday->kickoff_at), {{ $nextMatchday->kickoff_at->format('d.m H:i') }}@endif
                            </a>
                            @if ($league['rival'])
                                <span>{{ __('Rival') }}:
                                    @if ($league['rival']['url'])
                                        <a href="{{ $league['rival']['url'] }}" wire:navigate @class(['font-semibold text-ui-ink hover:text-club hover:underline', 'italic' => $league['rival']['bot']])>{{ $league['rival']['name'] }}</a>
                                    @else
                                        <span class="font-semibold text-ui-ink">{{ $league['rival']['name'] }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="mt-auto flex items-center justify-between gap-3 border-t border-ui-line px-5 py-3">
                        <span class="flex items-center gap-3 text-xs text-ui-muted">
                            @if (collect($league['rows'])->contains('zone', 'up'))
                                <span class="inline-flex items-center gap-1"><span class="h-[3px] w-3 rounded-full bg-green-500"></span>{{ __('Promotion') }}</span>
                            @endif
                            @if (collect($league['rows'])->contains('zone', 'down'))
                                <span class="inline-flex items-center gap-1"><span class="h-[3px] w-3 rounded-full bg-red-500"></span>{{ __('Relegation') }}</span>
                            @endif
                        </span>
                        <a href="{{ $league['url'] }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-club hover:underline">
                            {{ __('Full table') }} <x-ui.icon name="arrow-right" variant="micro" />
                        </a>
                    </div>
                </section>
            @endif

            @if ($stats = $this->seasonStats)
                {{-- Ciekawostki sezonu: celność typów i najskuteczniejsi --}}
                <section data-share-target class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
                    <div class="flex items-center justify-between gap-3 border-b border-ui-line px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-widest text-club">{{ trans_choice('After :count matchday|After :count matchdays', $stats['matchdays'], ['count' => $stats['matchdays']]) }}</p>
                            <h2 class="font-display text-lg font-semibold">{{ __('Season facts') }}</h2>
                        </div>
                        <x-share-image :title="__('Season facts')" class="text-ui-muted" />
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-ui-line border-b border-ui-line text-center">
                        @foreach ([[$stats['outcome'] . '%', __('right outcomes')], [$stats['exact'] . '%', __('exact scores')], [$stats['exact_count'], __('exact hits')]] as [$value, $label])
                            <div class="px-2 py-4">
                                <div class="font-display text-2xl font-bold tabular-nums text-club">{{ $value }}</div>
                                <div class="text-[11px] font-semibold uppercase leading-tight tracking-wide text-ui-muted">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="p-3">
                        <p class="px-2 pb-2 text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Best tipsters') }}</p>
                        <ol class="space-y-1">
                            @foreach ($stats['top'] as $row)
                                <li wire:key="top-{{ $row['user_id'] }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm odd:bg-ui-raised/40">
                                    <span @class([
                                        'grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold',
                                        'bg-amber-400 text-amber-950' => $loop->index === 0,
                                        'bg-zinc-300 text-zinc-800' => $loop->index === 1,
                                        'bg-orange-300 text-orange-950' => $loop->index === 2,
                                        'bg-ui-raised' => $loop->index > 2,
                                    ])>{{ $loop->iteration }}</span>
                                    <span class="min-w-0 flex-1 truncate font-semibold">{{ $row['team'] }}</span>
                                    <span class="text-xs tabular-nums text-ui-muted" title="{{ __('exact scores') }}">🎯 {{ $row['exact'] }}</span>
                                    <span class="w-12 text-right font-display font-bold tabular-nums">{{ $row['percent'] }}%</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </section>
            @else
                {{-- Przed pierwszą rozegraną kolejką: jak grać w trzech krokach --}}
                <section class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-club">#LechTYPER</p>
                    <h2 class="font-display text-lg font-semibold">{{ __('How to play') }}</h2>
                    <ol class="mt-4 space-y-4">
                        @foreach ([
                            ['pencil-square', __('Tip the score'), __('Before kickoff tip the score of the Lech match. One tip counts in every competition you play.')],
                            ['user-group', __('Pick Lech players'), __('Field Lech players for each competition: goals and assists add attack, clean sheets stop the rival.')],
                            ['trophy', __('Win trophies'), __('Climb the leagues, go deep in the cups and collect Hall of Fame points season after season.')],
                        ] as [$icon, $title, $text])
                            <li class="flex gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-club/10 text-club"><x-ui.icon :name="$icon" class="size-5" /></span>
                                <span>
                                    <span class="block font-semibold">{{ $loop->iteration }}. {{ $title }}</span>
                                    <span class="block text-sm text-ui-muted">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                    <a href="{{ route('howto') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-club hover:underline">{{ __('Guide for beginners') }} <x-ui.icon name="arrow-right" variant="micro" /></a>
                </section>
            @endif
        </div>

        {{-- Kawka (18.11.2026): dobrowolne wsparcie projektu, pod tabelą ligi i ciekawostkami --}}
        <x-coffee-button variant="banner" />
    @else
        {{-- Bez aktywnego sezonu: duży baner zapraszający do gry (z zapowiedzią sezonu w przygotowaniu) --}}
        <section class="lech-banner relative overflow-hidden rounded-2xl px-6 py-10 sm:px-10 sm:py-12">
            <div class="pointer-events-none absolute -right-16 -top-16 size-80 rounded-full bg-lech-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/3 size-72 rounded-full bg-sky-400/10 blur-3xl"></div>
            <x-app-logo-icon class="pointer-events-none absolute -right-6 bottom-0 hidden size-72 text-white opacity-[0.07] md:block" />
            <div class="relative max-w-2xl space-y-5">
                @if ($this->upcoming)
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-widest text-lech-100">
                        <span class="size-2 animate-pulse rounded-full bg-green-400"></span>
                        {{ $this->upcoming->title }} · {{ __('Coming soon') }}
                    </span>
                @else
                    <p class="text-sm font-semibold uppercase tracking-wider text-lech-200">#LechTYPER</p>
                @endif
                <h1 class="font-display text-3xl font-bold leading-tight tracking-tight sm:text-4xl">{{ __('Tip Lech. Pick your squad. Win trophies.') }}</h1>
                <p class="text-[15px] text-lech-100 sm:text-base">{{ __('One tip per Lech match decides your games in a league, the Polish Cup, European cups and Liga Legend. Pick Lech players, collect experience and climb from Liga podwórkowa to Ekstraklasa.') }}</p>
                <div class="flex flex-wrap gap-3 pt-2">
                    @guest
                        <a href="{{ route('register') }}" class="rounded-full bg-white px-6 py-3 text-sm font-semibold uppercase tracking-wide text-lech-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">{{ __('Join the game') }}</a>
                    @else
                        <a href="{{ route('tips') }}" wire:navigate class="rounded-full bg-white px-6 py-3 text-sm font-semibold uppercase tracking-wide text-lech-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">{{ __('My LechTYPER') }}</a>
                    @endguest
                    <a href="{{ route('rules') }}" wire:navigate class="rounded-full border border-white/30 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10">{{ __('Rules of the game') }}</a>
                    <a href="{{ route('history') }}" wire:navigate class="rounded-full border border-white/30 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10">{{ __('History and records') }}</a>
                </div>
            </div>
        </section>
    @endif

    @unless ($this->season)
        {{-- Jak to działa: trzy kroki --}}
        <section class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['pencil-square', __('Tip the score'), __('Before kickoff tip the score of the Lech match. One tip counts in every competition you play.')],
                ['user-group', __('Pick Lech players'), __('Field Lech players for each competition: goals and assists add attack, clean sheets stop the rival.')],
                ['trophy', __('Win trophies'), __('Climb the leagues, go deep in the cups and collect Hall of Fame points season after season.')],
            ] as $index => [$icon, $title, $text])
                <div class="relative overflow-hidden rounded-2xl border border-ui-line bg-ui-surface p-5">
                    <span class="pointer-events-none absolute -right-2 -top-6 select-none font-display text-7xl font-bold text-lech-700/5 dark:text-white/5">{{ $index + 1 }}</span>
                    <x-ui.icon :name="$icon" class="size-7 text-club" />
                    <h3 class="mt-3 font-display text-base font-semibold">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-ui-muted">{{ $text }}</p>
                </div>
            @endforeach
        </section>
    @endunless

    {{-- Ostatni mistrzowie w barwach rozgrywek --}}
    @if ($this->champions['rows'] !== [])
        @php
            $trophyIcons = \App\Support\HallOfFame::iconUrls();
        @endphp
        <section data-share-target class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div class="flex items-end gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-club">{{ $this->champions['season']->title }}</p>
                        <h2 class="font-display text-xl font-semibold">{{ __('Latest champions') }}</h2>
                    </div>
                    <x-share-image :title="__('Latest champions').' '.$this->champions['season']->title" class="text-ui-muted" />
                </div>
                <a href="{{ route('history') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-club hover:underline">
                    {{ __('Full history') }} <x-ui.icon name="arrow-right" variant="micro" />
                </a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->champions['rows'] as $row)
                    @php
                        $ck = \App\Support\CompetitionHistory::colorKey($row['key']);
                    @endphp
                    <a href="{{ route('history', \App\Support\CompetitionHistory::slug($row['key'])) }}" wire:navigate
                        class="comp-banner group flex items-center gap-4 rounded-2xl px-4 py-4 transition hover:brightness-110" style="{{ \App\Support\CompetitionColors::style($ck) }}">
                        <span class="comp-badge relative z-10 grid size-12 shrink-0 place-items-center rounded-full">
                            @if (isset($trophyIcons[$row['key']]))
                                <img src="{{ $trophyIcons[$row['key']] }}" alt="" class="size-10 object-contain">
                            @else
                                <x-ui.icon name="trophy" class="size-7" />
                            @endif
                        </span>
                        <span class="relative z-10 min-w-0">
                            <span class="comp-accent block text-[11px] font-bold uppercase tracking-widest">{{ $row['label'] }}</span>
                            <span class="{{ $row['team']['bot'] ? 'italic' : '' }} block truncate font-display text-base font-semibold">{{ $row['team']['name'] }}</span>
                            <span class="block text-xs opacity-75">{{ $row['line'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif


    {{-- Newsy z panelu z ocenami kciukami: siatka 3 x 5 z paginacją --}}
    <livewire:pages::home.news />

    {{-- Liczby serwisu (zmieniają się rzadko, więc na dole); nowa wartość dochodzi płynnie --}}
    <section wire:poll.60s.visible class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($this->numbers as $number)
            <div wire:key="number-{{ $loop->index }}" x-data="uiTween" data-to="{{ $number['value'] }}" :class="bump && 'ring-2 ring-club/50'"
                class="flex items-center gap-3 rounded-2xl border border-ui-line bg-ui-surface p-4 transition">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ui-raised">
                    <x-ui.icon :name="$number['icon']" class="size-5 text-club" />
                </span>
                <div class="min-w-0">
                    <div class="font-display text-xl font-bold leading-none tabular-nums text-ui-ink" x-text="shown">{{ number_format($number['value'], 0, ',', ' ') }}</div>
                    <div class="text-[11px] font-semibold uppercase leading-tight tracking-wide text-ui-muted">{{ $number['label'] }}</div>
                </div>
            </div>
        @endforeach
    </section>

</div>
