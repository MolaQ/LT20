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

    /** Liczby serwisu do paska pod banerem: gracze, zakończone sezony, rozegrane kolejki, zdobyte trofea. */
    #[Computed]
    public function numbers(): array
    {
        return [
            ['icon' => 'users', 'value' => \App\Support\Players::eligible()->count(), 'label' => __('Players')],
            ['icon' => 'calendar', 'value' => Season::where('status', SeasonStatus::Finished->value)->count(), 'label' => __('Seasons played')],
            ['icon' => 'flag', 'value' => Matchday::where('status', MatchdayStatus::Played->value)->count(), 'label' => __('Lech matches tipped')],
            ['icon' => 'trophy', 'value' => \App\Models\HallOfFameAward::whereNotNull('trophy')->count(), 'label' => __('Trophies won')],
        ];
    }

    /** Ostatni mistrzowie najważniejszych rozgrywek (App\Support\CompetitionHistory). */
    #[Computed]
    public function champions(): array
    {
        return \App\Support\CompetitionHistory::latestChampions();
    }

    /**
     * Stan typu zalogowanego gracza w tej kolejce: odpowiedzi na pytania i wystawieni zawodnicy (wszystkie typy rozgrywek).
     *
     * @return array{answered: int, questions: int, players: int}|null
     */
    #[Computed]
    public function myTipStatus(): ?array
    {
        if (!$this->matchday || !auth()->check()) {
            return null;
        }

        $questionIds = \App\Models\MatchdayQuestion::where('matchday_id', $this->matchday->id)->pluck('id');

        return [
            'answered' => $questionIds->isEmpty() ? 0 : \App\Models\QuestionAnswer::whereIn('matchday_question_id', $questionIds)->where('user_id', auth()->id())->count(),
            'questions' => $questionIds->count(),
            'players' => \App\Models\DesignatedPlayer::where('matchday_id', $this->matchday->id)->where('user_id', auth()->id())->count(),
        ];
    }

    /**
     * Tabela ligi zalogowanego gracza w bieżącym sezonie: cała liga (10 zespołów), w Lidze podwórkowej
     * tylko dwa miejsca nad i pod graczem (tam zespołów są setki).
     *
     * @return array{name: string, url: string, rows: array<int, array<string, mixed>>}|null
     */
    #[Computed]
    public function myLeague(): ?array
    {
        if (!$this->season || !auth()->check()) {
            return null;
        }

        $team = \App\Models\SeasonTeam::where('season_id', $this->season->id)->where('user_id', auth()->id())->first();
        $entry = $team ? \App\Models\CompetitionEntry::with('competition')->where('season_team_id', $team->id)
            ->whereHas('competition', fn($q) => $q->where('season_id', $this->season->id)->whereIn('type', [\App\Enums\CompetitionType::League->value, \App\Enums\CompetitionType::Swiss->value]))
            ->first() : null;

        if (!$entry) {
            return null;
        }

        $competition = $entry->competition;
        $rows = \App\Support\Standings::for($competition)->values()->map(fn($row, $index) => [
            'place' => $index + 1,
            'name' => $row['team']?->name ?? '—',
            'bot' => (bool) $row['team']?->is_bot,
            'played' => $row['played'],
            'goals' => $row['for'] . ':' . $row['against'],
            'points' => $row['points'],
            'me' => $row['entry_id'] === $entry->id,
        ]);

        if ($competition->type === \App\Enums\CompetitionType::Swiss) {
            $mine = $rows->search(fn($row) => $row['me']);
            $rows = $rows->slice(max(0, $mine - 2), 5);
        }

        return [
            'name' => $competition->name ?: $competition->type->label(),
            'url' => route('results', ['season_slug' => 'sezon-' . $this->season->number, 'competition_slug' => $competition->slug()]) . '?team=' . $team->id,
            'rows' => $rows->values()->all(),
        ];
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
                    <div class="flex items-center gap-2 text-xs">
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

                <div class="space-y-5 px-5 py-5 sm:px-8">
                    {{-- Tablica: gospodarz, wynik albo „vs”, gość --}}
                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-6">
                        @foreach ($sides as $index => $side)
                            @if ($index === 1)
                                <div class="flex flex-col items-center gap-1">
                                    @if ($played)
                                        <div class="{{ $lechWon ? 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200' : ($lechLost ? 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200') }} flex items-center gap-2 rounded-xl px-4 py-1.5 font-display text-3xl font-bold tabular-nums sm:text-4xl">
                                            <span>{{ $sides[0]['goals'] }}</span><span class="opacity-60">:</span><span>{{ $sides[1]['goals'] }}</span>
                                        </div>
                                    @else
                                        <div class="rounded-lg bg-ui-raised px-3 py-1.5 text-sm font-semibold uppercase tracking-widest text-ui-muted">vs</div>
                                    @endif
                                </div>
                            @endif
                            <div class="{{ $index === 0 ? 'items-end text-right' : 'items-start text-left' }} flex min-w-0 flex-col gap-1">
                                <span class="{{ $side['lech'] ? 'text-club' : 'text-ui-ink' }} truncate font-display text-base font-semibold sm:text-xl">{{ $side['name'] }}</span>
                                <span class="rounded bg-ui-raised px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-ui-muted">{{ $index === 0 ? __('HOME') : __('AWAY') }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Protokół meczu (tylko zawodnicy Lecha) --}}
                    @php
                        $protocol = $this->protocolOf($played ? $matchday : null);
                    @endphp
                    @if ($protocol->isNotEmpty())
                        <div class="mx-auto w-full max-w-md rounded-xl border border-ui-line px-3 py-1">
                            @include('partials.match-protocol', ['events' => $protocol])
                        </div>
                    @endif

                    @if ($matchday->kickoff_at)
                        <div class="flex items-center justify-center gap-1.5 text-sm text-ui-muted">
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
                            }" x-init="tick(); setInterval(() => tick(), 1000)" class="space-y-2 text-center">
                            <div class="text-xs font-semibold uppercase tracking-wider text-ui-muted">{{ __('Tipping closes in') }}</div>
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
                                        <div class="flex gap-[3px] text-3xl sm:text-4xl" role="timer" aria-label="{{ $unit }}">
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
                                        <div class="text-[11px] font-medium uppercase tracking-wider text-ui-muted">{{ $unit }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Akcje: typowanie albo wyniki --}}
                    <div class="flex flex-wrap items-center justify-center gap-2 border-t border-ui-line pt-4">
                        @if ($played)
                            <x-ui.btn :href="route('results')" wire:navigate icon="trophy">{{ __('Results and tables') }}</x-ui.btn>
                        @elseif (!auth()->check())
                            <x-ui.btn variant="primary" :href="route('login')" wire:navigate>{{ __('Log in to tip') }}</x-ui.btn>
                        @elseif ($matchday->isOpenForTips())
                            @if ($this->myTip)
                                <x-ui.badge color="green">{{ __('Your tip: :score', ['score' => $this->myTip->score()]) }}</x-ui.badge>
                            @endif
                            {{-- Czego jeszcze brakuje w typie: pytania i zawodnicy --}}
                            @if ($status = $this->myTipStatus)
                                @if ($status['questions'] > 0)
                                    <x-ui.badge :color="$status['answered'] >= $status['questions'] ? 'green' : 'amber'">{{ __('Questions: :answered/:count', ['answered' => $status['answered'], 'count' => $status['questions']]) }}</x-ui.badge>
                                @endif
                                <x-ui.badge :color="$status['players'] > 0 ? 'green' : 'zinc'">{{ __('Players: :count', ['count' => $status['players']]) }}</x-ui.badge>
                            @endif
                            <x-ui.btn variant="primary" icon="pencil-square"
                                :href="route('tips', ['matchday_slug' => 'kolejka-' . $matchday->number])" wire:navigate>
                                {{ $this->myTip ? __('Change tip') : __('Tip now') }}
                            </x-ui.btn>
                        @else
                            @if ($this->myTip)
                                <x-ui.badge color="zinc">{{ __('Your tip: :score', ['score' => $this->myTip->score()]) }}</x-ui.badge>
                            @endif
                            <x-ui.btn :href="route('results')" wire:navigate icon="trophy">{{ __('Results and tables') }}</x-ui.btn>
                        @endif
                    </div>
                </div>
            </section>

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

        {{-- Tabela ligi gracza (cała liga, w Lidze podwórkowej miejsca wokół gracza) --}}
        @if ($league = $this->myLeague)
            <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
                <div class="flex items-center justify-between gap-2 px-5 pb-2 pt-4">
                    <x-ui.heading>{{ $league['name'] }}</x-ui.heading>
                    <a href="{{ $league['url'] }}" wire:navigate class="text-xs font-semibold text-club hover:underline">{{ __('Full table') }}</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead>
                            <tr class="text-left text-[11px] text-ui-muted">
                                <th class="px-5 py-1.5 font-medium">#</th>
                                <th class="py-1.5 font-medium">{{ __('Team') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium">{{ __('M') }}</th>
                                <th class="px-2 py-1.5 text-right font-medium">{{ __('Goals') }}</th>
                                <th class="px-5 py-1.5 text-right font-medium">{{ __('Pts') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($league['rows'] as $row)
                                <tr wire:key="league-row-{{ $row['place'] }}" @class([
                                    'border-t border-ui-line',
                                    'bg-club/10 shadow-[inset_2px_0_0_var(--ui-club)]' => $row['me'],
                                ])>
                                    <td class="px-5 py-2 text-ui-muted">{{ $row['place'] }}</td>
                                    <td @class(['max-w-0 truncate py-2 font-medium', 'italic' => $row['bot']])>{{ $row['name'] }}</td>
                                    <td class="px-2 py-2 text-right text-ui-muted">{{ $row['played'] }}</td>
                                    <td class="px-2 py-2 text-right text-ui-muted">{{ $row['goals'] }}</td>
                                    <td class="px-5 py-2 text-right font-semibold">{{ $row['points'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
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

    {{-- Liczby serwisu --}}
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($this->numbers as $number)
            <div class="group flex items-center gap-3 rounded-2xl border border-ui-line bg-ui-surface p-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ui-raised">
                    <x-ui.icon :name="$number['icon']" class="size-5 text-club" />
                </span>
                <div class="min-w-0">
                    <div class="font-display text-xl font-bold leading-none tabular-nums text-ui-ink">{{ number_format($number['value'], 0, ',', ' ') }}</div>
                    <div class="text-[11px] font-semibold uppercase leading-tight tracking-wide text-ui-muted">{{ $number['label'] }}</div>
                </div>
            </div>
        @endforeach
    </section>

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

</div>
