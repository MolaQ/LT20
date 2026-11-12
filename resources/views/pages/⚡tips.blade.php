<?php

use App\Actions\Tips\SaveTip;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamScore;
use App\Models\Tip;
use App\Support\Players;
use App\Support\TipRules;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Typowanie gracza: wynik meczu Lecha (jeden typ na wszystkie rozgrywki) i desygnowanie zawodników
 * (pages::home.designation-panels). Zapis możliwy do godziny pierwszego gwizdka.
 * Uwaga: nie nazywaj własności "slots" ani "rows" – Livewire rezerwuje część nazw.
 */
new #[Layout('layouts::public')] class extends Component {
    public int $number = 0;

    /** Zakładka: tip (typowanie), competitions (rozgrywki), stats (statystyki), club (mój klub), history (historia), scalps (moje skalpy), blacklist (czarna lista). */
    public string $tab = 'tip';

    /** Zakładki w adresie po polsku: /tips/kolejka-3/statystyki (typowanie bez części zakładki). */
    private const TAB_SLUGS = [
        'tip' => 'typ', 'competitions' => 'rozgrywki',
        'stats' => 'statystyki', 'club' => 'moja-kadra', 'history' => 'historia', 'scalps' => 'skalpy', 'blacklist' => 'czarna-lista',
    ];

    /** Wpisany wynik (teksty, bo pole formularza może być puste). */
    public string $lech = '';
    public string $opponent = '';

    /** Krok kreatora typowania (wygląd 2.0): score (wynik), players (zawodnicy), ticket (bilet). */
    public string $step = 'score';

    /** Godzina ostatniego automatycznego zapisu (do napisu „Zapisano” w pasku na dole). */
    public string $savedTime = '';

    /** Przyjazny adres albo stary z parametrami (?matchday=3&tab=stats), np. z zapisanych linków. */
    public function mount(?string $matchday_slug = null, ?string $tab_slug = null): void
    {
        $this->number = (int) str_replace('kolejka-', '', $matchday_slug ?? (string) request()->query('matchday', '0'));
        $tab = $tab_slug !== null ? array_search($tab_slug, self::TAB_SLUGS, true) : request()->query('tab', 'tip');
        $this->tab = is_string($tab) && isset(self::TAB_SLUGS[$tab]) ? $tab : 'tip';

        if ($this->number < 1 || !$this->matchdays->firstWhere('number', $this->number)) {
            // Domyślnie pierwsza kolejka, na którą można jeszcze typować; w razie braku pierwsza.
            $open = $this->matchdays->first(fn($m) => $m->isOpenForTips());
            $this->number = $open?->number ?? ($this->matchdays->first()?->number ?? 0);
        }

        $this->loadTip();
        $this->ensureTip();
    }

    public function render(): View
    {
        return $this->view()->title('#LechTYPER');
    }

    /** Adres bieżącej kolejki i zakładki. */
    private function pageUrl(): string
    {
        $params = ['matchday_slug' => 'kolejka-'.$this->number];

        if ($this->tab !== 'tip') {
            $params['tab_slug'] = self::TAB_SLUGS[$this->tab] ?? 'typ';
        }

        return $this->number > 0 ? route('tips', $params) : route('tips');
    }

    /** Podmienia adres w pasku przeglądarki bez przeładowania strony. */
    private function syncUrl(): void
    {
        $this->js('history.replaceState(history.state, "", '.json_encode($this->pageUrl()).')');
    }

    public function updatedTab(): void
    {
        $this->syncUrl();
        $this->ensureTip();
    }

    /**
     * Pierwsze otwarcie zakładki Typowanie dla otwartej kolejki zapisuje typ 0:0.
     * Gracz zmienia go przyciskami, a numer zapisu (remisy w pucharach) liczy się od ostatniej zmiany.
     */
    private function ensureTip(): void
    {
        if ($this->tab !== 'tip' || !$this->canPlay || !$this->isOpen || $this->tip !== null || !$this->matchday?->isFilled()) {
            return;
        }

        $this->lech = '0';
        $this->opponent = '0';
        $this->autosave();
    }

    public function updatedNumber(): void
    {
        $this->syncUrl();

        unset($this->matchday, $this->tip, $this->isOpen, $this->scores, $this->crowd, $this->ticket, $this->questions, $this->myAnswers);
        $this->loadTip();
        $this->ensureTip();

        // Panel rywali po prawej pokazuje tę samą kolejkę.
        $this->dispatch('matchday-selected', number: $this->number);
    }

    /** Wczytuje zapisany typ gracza do pól formularza. */
    private function loadTip(): void
    {
        $this->lech = '';
        $this->opponent = '';
        $this->savedTime = '';

        $matchday = $this->matchday;

        if (!$matchday) {
            return;
        }

        $tip = Tip::where('matchday_id', $matchday->id)->where('user_id', auth()->id())->first();

        if ($tip) {
            $this->lech = (string) $tip->lech_goals;
            $this->opponent = (string) $tip->opponent_goals;
        }
    }

    /* ==================================================================
     | DANE
     * ================================================================*/

    #[Computed]
    public function season(): ?Season
    {
        return Season::current();
    }

    /** Czy to konto w ogóle może grać w tym sezonie (rola, brak bana, jest na liście)? */
    #[Computed]
    public function canPlay(): bool
    {
        $user = auth()->user();

        return $this->season
            && Players::canPlay($user)
            && SeasonTeam::where('season_id', $this->season->id)->where('user_id', $user->id)->exists();
    }

    #[Computed]
    public function matchdays()
    {
        return $this->season
            ? Matchday::where('season_id', $this->season->id)->orderBy('number')->get()
            : collect();
    }

    /**
     * Kafelki kolejek: liczba typów graczy i odsetek trafionych rozstrzygnięć (po wyniku) dla każdej kolejki sezonu.
     *
     * @return array<int, array{tips: int, outcome: int|null}>
     */
    #[Computed]
    public function matchdayTipStats(): array
    {
        if (!$this->season) {
            return [];
        }

        $rows = Tip::query()
            ->join('matchdays', 'matchdays.id', '=', 'tips.matchday_id')
            ->where('matchdays.season_id', $this->season->id)
            ->groupBy('tips.matchday_id')
            ->selectRaw('tips.matchday_id, COUNT(*) AS tips_count, SUM(CASE
                WHEN tips.lech_goals > tips.opponent_goals AND matchdays.lech_goals > matchdays.opponent_goals THEN 1
                WHEN tips.lech_goals = tips.opponent_goals AND matchdays.lech_goals = matchdays.opponent_goals THEN 1
                WHEN tips.lech_goals < tips.opponent_goals AND matchdays.lech_goals < matchdays.opponent_goals THEN 1
                ELSE 0 END) AS outcome_hits')
            ->toBase()
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->matchday_id] = ['tips' => (int) $row->tips_count, 'hits' => (int) $row->outcome_hits];
        }

        $out = [];
        foreach ($this->matchdays as $matchday) {
            $count = $stats[$matchday->id]['tips'] ?? 0;
            $played = $matchday->status === \App\Enums\MatchdayStatus::Played && $matchday->lech_goals !== null;
            $out[$matchday->id] = [
                'tips' => $count,
                'outcome' => $played && $count > 0 ? (int) round(100 * ($stats[$matchday->id]['hits'] ?? 0) / $count) : null,
            ];
        }

        return $out;
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return $this->matchdays->firstWhere('number', $this->number);
    }

    #[Computed]
    public function isOpen(): bool
    {
        return $this->matchday?->isOpenForTips() ?? false;
    }

    #[Computed]
    public function tip(): ?Tip
    {
        return $this->matchday
            ? Tip::where('matchday_id', $this->matchday->id)->where('user_id', auth()->id())->first()
            : null;
    }

    /* Zakładki ze statystykami (App\Support\PlayerStats). */

    /** Sezon zakładek ze statystykami: aktywny, a bez niego ostatni zakończony (zakładki są widoczne także między sezonami). */
    #[Computed]
    public function statsSeason(): ?Season
    {
        return $this->season ?? Season::where('status', \App\Enums\SeasonStatus::Finished->value)->orderByDesc('number')->first();
    }

    #[Computed]
    public function myCompetitions(): array
    {
        return $this->statsSeason ? \App\Support\PlayerStats::competitions(auth()->user(), $this->statsSeason) : [];
    }

    /** Zawodnicy klubu gracza z dorobkiem (zakładka „Mój klub”, Support\ClubStats). */
    #[Computed]
    public function myClub(): array
    {
        return \App\Support\ClubStats::players(auth()->id(), null, $this->statsSeason?->id);
    }

    #[Computed]
    public function myStats(): array
    {
        return $this->statsSeason ? \App\Support\PlayerStats::season(auth()->user(), $this->statsSeason) : [];
    }

    /** Ciekawostki: ulubione typy, najlepszy sezon, a dla premium typy w rozgrywkach. */
    #[Computed]
    public function myExtras(): array
    {
        $premium = \App\Support\Premium::isActive(auth()->user());

        return [
            'favourites' => \App\Support\PlayerStats::favourites(auth()->user()),
            'best_season' => \App\Support\PlayerStats::bestSeason(auth()->user()),
            'competitions' => $premium && $this->statsSeason ? \App\Support\PlayerStats::competitionFavourites($this->statsSeason) : [],
        ];
    }

    #[Computed]
    public function myHistory(): array
    {
        return \App\Support\PlayerStats::history(auth()->user());
    }

    public function openMatchday(int $number): void
    {
        $this->tab = 'tip';
        $this->number = $number;
        $this->updatedNumber();
    }

    /** Moje skalpy (premium): gracze z gorszym bilansem bezpośrednim, od najwyżej w rankingu. */
    #[Computed]
    public function myScalps(): array
    {
        return \App\Support\Premium::isActive(auth()->user()) ? \App\Support\PlayerStats::scalps(auth()->user()) : [];
    }

    /**
     * „Twoje ligowe mecze” pod typowaniem: liga zespołu (1-10 albo podwórkowa) w sezonie i jego mecze w kolejkach,
     * w formacie jak „Twoja droga w pucharze” (partials/my-matches-path).
     *
     * @return array{competition: \App\Models\Competition, entry_id: int, path: array<int, list<\App\Models\Fixture>>, status: string}|null
     */
    #[Computed]
    public function myLeague(): ?array
    {
        $teamId = $this->season ? SeasonTeam::where('season_id', $this->season->id)->where('user_id', auth()->id())->value('id') : null;

        $entry = $teamId ? \App\Models\CompetitionEntry::with('competition')
            ->where('season_team_id', $teamId)
            ->whereHas('competition', fn ($q) => $q->whereIn('type', [\App\Enums\CompetitionType::League->value, \App\Enums\CompetitionType::Swiss->value]))
            ->first() : null;

        if (!$entry || !$entry->competition) {
            return null;
        }

        $path = [];
        foreach (\App\Models\Fixture::with(['home.seasonTeam.user:id,name,team_name', 'home.seasonTeam.bot:id,name', 'away.seasonTeam.user:id,name,team_name', 'away.seasonTeam.bot:id,name'])
            ->where('competition_id', $entry->competition_id)
            ->where(fn ($q) => $q->where('home_entry_id', $entry->id)->orWhere('away_entry_id', $entry->id))
            ->orderBy('round')
            ->get() as $fixture) {
            $path[$fixture->round][] = $fixture;
        }

        $table = \App\Support\Standings::for($entry->competition)->values();
        $index = $table->search(fn ($row) => $row['entry_id'] === $entry->id);

        return [
            'competition' => $entry->competition,
            'entry_id' => (int) $entry->id,
            'path' => $path,
            'status' => $index === false ? '' : __(':place. place of :count', ['place' => $index + 1, 'count' => $table->count()]),
        ];
    }

    /** Czarna lista (premium): rywale, z którymi gracz ma ujemny bilans bezpośredni. */
    #[Computed]
    public function myBlacklist(): array
    {
        return \App\Support\Premium::isActive(auth()->user()) ? \App\Support\PlayerStats::scalps(auth()->user(), blacklist: true) : [];
    }

    /** Czy gracz ma aktywne premium (statystyki typów innych graczy). */
    #[Computed]
    public function premium(): bool
    {
        return \App\Support\Premium::isActive(auth()->user());
    }

    /** Premium: jak typują inni gracze (rozkład Lech / remis / rywal, najczęstsze typy), także przed pierwszym gwizdkiem. */
    #[Computed]
    public function crowd(): ?array
    {
        return $this->premium && $this->matchday ? \App\Support\MatchdayInsights::tips($this->matchday) : null;
    }

    /** Rozliczenie kolejki po wpisaniu wyniku: typ rozgrywek (score_set) => TeamScore. */
    #[Computed]
    public function scores()
    {
        if (!$this->matchday || $this->matchday->status !== \App\Enums\MatchdayStatus::Played) {
            return collect();
        }

        // Rozliczenie gracza z tej kolejki (gdy wtedy grał za niego bot, nic nie pokazujemy, Ownership).
        return TeamScore::where('matchday_id', $this->matchday->id)->where('user_id', auth()->id())->get()
            ->keyBy(fn($score) => $score->score_set->value);
    }

    /**
     * Bilet: wystawieni zawodnicy w każdym typie rozgrywek gracza (etykieta typu => nazwiska po przecinku).
     *
     * @return list<array{label: string, players: string}>
     */
    #[Computed]
    public function ticket(): array
    {
        if (!$this->matchday) {
            return [];
        }

        $picks = \App\Support\Squad::picks(auth()->id(), null, $this->matchday);
        $names = \App\Models\LechPlayer::whereIn('id', collect($picks)->flatten()->all())->pluck('name', 'id');
        $sets = \App\Support\PlayerCompetitions::types(auth()->id(), $this->matchday->season_id, $this->matchday->number);

        return collect($sets)->map(fn ($set) => [
            'label' => $set->label(),
            'players' => collect($picks[$set->value] ?? [])->map(fn ($id) => $names[$id] ?? '?')->implode(', '),
        ])->values()->all();
    }

    /** Pytania kolejki (Support\Questions). */
    #[Computed]
    public function questions()
    {
        return $this->matchday ? \App\Support\Questions::for($this->matchday) : collect();
    }

    /** Odpowiedzi gracza: id pytania => QuestionAnswer. */
    #[Computed]
    public function myAnswers()
    {
        return $this->matchday ? \App\Support\Questions::answers(auth()->id(), $this->matchday) : collect();
    }

    /* ==================================================================
     | AKCJE
     * ================================================================*/

    /*
     * Zapis automatyczny (bez przycisku): Livewire woła metody updated<Własność>() po każdej zmianie pola
     * powiązanego przez wire:model albo $set.
     */

    /** Zmiana liczby goli Lecha: poprawiamy wartość i zapisujemy typ. */
    public function updatedLech(): void
    {
        $this->autosave();
    }

    /** Zmiana liczby goli rywala: poprawiamy wartość i zapisujemy typ. */
    public function updatedOpponent(): void
    {
        $this->autosave();
    }

    /** Odpowiedź na pytanie: 'yes', 'no' albo '' (pominięcie). */
    public function answerQuestion(int $questionId, string $value): void
    {
        $question = $this->questions->firstWhere('id', $questionId);

        if (!$question || !$this->canPlay) {
            return;
        }

        try {
            \App\Support\Questions::answer(auth()->user(), $question, match ($value) {
                'yes' => true,
                'no' => false,
                default => null,
            });
        } catch (DomainException $e) {
            $this->dispatch('ui-toast', variant: 'danger', text: $e->getMessage());
        }

        unset($this->myAnswers);
    }

    /** Strona bonusu pytania: 'attack' albo 'defense'. */
    public function questionSide(int $questionId, string $side): void
    {
        $question = $this->questions->firstWhere('id', $questionId);

        if (!$question || !$this->canPlay) {
            return;
        }

        try {
            \App\Support\Questions::side(auth()->user(), $question, $side);
        } catch (DomainException $e) {
            $this->dispatch('ui-toast', variant: 'danger', text: $e->getMessage());
        }

        unset($this->myAnswers);
    }

    /** „Skasuj bilet”: typ i skład zapisują się na bieżąco, tu tylko potwierdzenie z pieczątką i konfetti. */
    public function punch(): void
    {
        if (!$this->isOpen || !$this->tip) {
            return;
        }

        $this->dispatch('ticket-punched');
        $this->dispatch('ui-toast', text: __('Tip saved! You can change it until kick-off.'));
        $this->js('setTimeout(() => window.ui.confetti(110), 380)');
    }

    /** Przyciski + / − pod liczbą goli Lecha ('lech') albo rywala ('opponent'). */
    public function bump(string $side, int $delta): void
    {
        if (!in_array($side, ['lech', 'opponent'], true) || !$this->isOpen) {
            return;
        }

        $this->{$side} = (string) max(0, min(TipRules::MAX_GOALS, (int) $this->{$side} + ($delta > 0 ? 1 : -1)));
        $this->autosave();
    }

    /** Szybki wynik jednym kliknięciem (np. 2:1). */
    public function pick(int $lech, int $opponent): void
    {
        if (!$this->isOpen) {
            return;
        }

        $this->lech = (string) max(0, min(TipRules::MAX_GOALS, $lech));
        $this->opponent = (string) max(0, min(TipRules::MAX_GOALS, $opponent));
        $this->autosave();
    }

    /**
     * Gole z formularza do zapisu: puste pole -> 0,
     * liczba ujemna -> wartość bezwzględna (abs), wszystko, co dalej nie spełnia TipRules::goals()
     * (tekst, ułamek, więcej niż TipRules::MAX_GOALS), -> 0.
     */
    private static function normalizeGoals(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '0';
        }

        if (preg_match('/^-\d+$/', $value)) {
            $value = (string) abs((int) $value);
        }

        return (string) (\App\Support\TipRules::goals($value) ?? 0);
    }

    /** Zapisuje typ przez SaveTip (te same zasady co wcześniej przycisk „Zapisz typ”). */
    private function autosave(): void
    {
        $matchday = $this->matchday;

        if (!$matchday || !$this->isOpen) {
            return;
        }

        $this->lech = self::normalizeGoals($this->lech);
        $this->opponent = self::normalizeGoals($this->opponent);

        try {
            app(SaveTip::class)->handle(auth()->user(), $matchday, $this->lech, $this->opponent);
        } catch (DomainException $e) {
            $this->dispatch('ui-toast', variant: 'danger', text: $e->getMessage());

            return;
        }

        // Odświeżamy wyliczone dane (typ, statystyki premium), ale nie pola formularza.
        unset($this->tip, $this->crowd);
        $this->savedTime = now()->format('H:i:s');
    }
}; ?>
<div class="flex w-full flex-col gap-6">
    {{-- Baner jak na stronie głównej --}}
    <x-page-banner :eyebrow="$this->season?->title ?? '#LechTYPER'" title="#LechTYPER"
        :subtitle="__('Tip the score of the Lech match. You can change your tip until kickoff.')" />

    @if (!$this->season)
        <x-ui.card>
            <x-ui.text>{{ __('There is no active season right now.') }}</x-ui.text>
            @if ($this->statsSeason)
                <x-ui.text class="text-xs">{{ __('Tipping starts with the next season. Below: your numbers from :season.', ['season' => $this->statsSeason->title]) }}</x-ui.text>
            @endif
        </x-ui.card>
    @elseif (!$this->canPlay)
        <x-ui.card class="space-y-1">
            <x-ui.heading>{{ __('You are not taking part in this season') }}</x-ui.heading>
            <x-ui.text>
                {{ __('Your account needs a role (without a ban) and a place on the team list. Contact the administrator.') }}
            </x-ui.text>
        </x-ui.card>
    @elseif ($this->matchdays->isEmpty() || !$this->matchday)
        <x-ui.card>
            <x-ui.text>{{ __('No matchdays yet.') }}</x-ui.text>
        </x-ui.card>
    @endif

    @php
        // Bez aktywnego sezonu (albo bez miejsca w nim) typowania nie ma, ale zakładki ze statystykami, kadrą i rekordami zostają
        // (statystyki i rozgrywki z ostatniego zakończonego sezonu, statsSeason).
        $tipping = $this->season && $this->canPlay && $this->matchday;
        $tabs = [
            'tip' => ['pencil-square', __('Tipping')],
            'competitions' => ['trophy', __('Competitions')],
            'stats' => ['chart-bar', __('Statistics')],
            'club' => ['user-group', __('My squad')],
            'history' => ['clock', __('History and records')],
            'scalps' => ['fire', __('My scalps')],
            'blacklist' => ['no-symbol', __('Blacklist')],
        ];
        if (!$tipping) {
            unset($tabs['tip']);
        }
        if (!$this->statsSeason) {
            unset($tabs['competitions'], $tabs['stats']);
        }
        if (!isset($tabs[$tab])) {
            $tab = array_key_first($tabs);
        }
        $played = $this->matchday?->status === \App\Enums\MatchdayStatus::Played;
    @endphp

    {{-- Zakładki: segmentowy przełącznik --}}
    {{-- Ostrzeżenie „kartki” po kolejkach bez typu (App\Support\Inactivity); przycisk niepotrzebny, bo to już typowanie. --}}
    <x-inactivity-warning :tip-link="false" />

    <nav class="flex gap-1 overflow-x-auto rounded-2xl bg-ui-raised p-1 [scrollbar-width:none]" data-no-swipe>
        @foreach ($tabs as $key => [$icon, $label])
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                class="{{ $tab === $key ? 'bg-ui-surface text-ui-ink shadow' : 'text-ui-muted hover:text-ui-ink' }} flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-2 text-sm font-bold transition">
                <x-ui.icon :name="$icon" variant="micro" />
                {{ $label }}
                @if (in_array($key, ['scalps', 'blacklist'], true))
                    <x-ui.icon name="sparkles" variant="micro" class="text-ui-muted" />
                @endif
            </button>
        @endforeach
    </nav>

    @if ($tab === 'tip')
    {{--
        Wygląd 2.0: typowanie jako kreator krok po kroku (Wynik → Zawodnicy → Bilet), wszystko kliknięciem.
        Pasek kolejek przewija się w bok; kropka = typowanie otwarte.
    --}}
    @php
        $md = $this->matchday;
        $statusChip = $this->isOpen ? ['good', __('Open')] : ($played ? ['club', __('Settled')] : ['default', __('Closed')]);
        $steps = ['score' => __('Score')]
            + ($this->questions->isNotEmpty() ? ['questions' => __('Questions')] : [])
            + ['players' => __('Squad picks'), 'ticket' => __('Ticket')];
        if (!isset($steps[$step])) {
            $step = 'score';
        }
    @endphp

    {{--
        Kolejki sezonu. Telefon: pasek przesuwany palcem (bez widocznego suwaka), wybrana kolejka na środku.
        Komputer: wszystkie kolejki w jednym rzędzie bez przewijania; wybrana jest szersza (pełna nazwa rywala),
        pozostałe zwarte: numer, wynik albo data, skrót rywala (pełna nazwa w podpowiedzi).
    --}}
    <div class="-mx-3.5 flex snap-x gap-2 overflow-x-auto px-3.5 pb-1 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-10 sm:gap-1.5 sm:overflow-visible sm:px-0 sm:pb-0" data-no-swipe
        x-init="if (window.innerWidth < 640) $el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' })">
        @foreach ($this->matchdays as $option)
            @php
                $hasResult = $option->status === \App\Enums\MatchdayStatus::Played && $option->lech_goals !== null;
                $selected = $option->number === $this->number;
                $opponent = filled($option->opponent) ? $option->opponent : '…';
            @endphp
            <button type="button" wire:click="openMatchday({{ $option->number }})" wire:key="md-{{ $option->id }}" @if ($selected) aria-current="true" @endif
                title="{{ __('Matchday :number', ['number' => $option->number]) }}: {{ $opponent }}"
                @class([
                    'flex w-28 shrink-0 snap-center flex-col items-start gap-0.5 rounded-xl border px-3 py-2 text-start transition sm:w-auto sm:min-w-0 sm:px-2.5',
                    'border-club bg-club text-club-ink shadow-sm sm:col-span-2' => $selected,
                    'border-ui-line bg-ui-surface hover:border-club/40 hover:bg-ui-raised/60' => ! $selected,
                ])>
                <span class="flex w-full items-center justify-between gap-1 text-xs font-medium opacity-80">
                    <span @class(['truncate', 'sm:hidden' => ! $selected])>{{ __('Matchday :number', ['number' => $option->number]) }}</span>
                    @unless ($selected)
                        <span class="hidden tabular-nums sm:inline">{{ $option->number }}.</span>
                    @endunless
                    @if (!$hasResult && $option->isOpenForTips())
                        <span class="size-2 shrink-0 rounded-full bg-good" title="{{ __('Tipping open') }}"></span>
                    @endif
                </span>
                <span @class(['w-full truncate font-medium', 'text-sm' => $selected, 'text-sm sm:text-xs' => ! $selected])>{{ $opponent }}</span>
                <span class="text-base font-semibold leading-tight tabular-nums">{{ $hasResult ? $option->lech_goals.':'.$option->opponent_goals : ($option->kickoff_at?->translatedFormat('j M') ?? '?:?') }}</span>
            </button>
        @endforeach
    </div>

    <x-ui.segmented wire:model.live="step" :options="$steps" />

    @if ($step === 'score')
        <x-ui.card hero>
            <div class="flex items-center justify-between gap-2 text-xs uppercase tracking-wider">
                <span class="opacity-85">{{ __('Matchday :number', ['number' => $md->number]) }} · {{ $md->kickoff_at ? $md->kickoff_at->translatedFormat('D j M, H:i') : __('not set yet') }}</span>
                <x-ui.chip :variant="$statusChip[0]">{{ $statusChip[1] }}</x-ui.chip>
            </div>
            @if ($md->competitionLabel())
                <div class="mt-0.5 text-xs opacity-70">{{ $md->competitionLabel() }}</div>
            @endif

            @if (!$md->isFilled())
                <p class="py-8 text-center opacity-85">{{ __('The opponent and date are not set yet, so tipping is not open.') }}</p>
            @else
                <div class="my-4 flex items-start justify-around">
                    <x-ui.counter wire:model.live="lech" :label="mb_strtoupper(config('club.short_name'))" :max="\App\Support\TipRules::MAX_GOALS" :disabled="!$this->isOpen" />
                    <span class="pt-12 font-display text-5xl font-extrabold opacity-60">:</span>
                    <x-ui.counter wire:model.live="opponent" :label="mb_strtoupper($md->opponent)" :max="\App\Support\TipRules::MAX_GOALS" :disabled="!$this->isOpen" />
                </div>

                @if ($played)
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-ui.chip variant="club">{{ __('Final score: :score.', ['score' => config('club.short_name').' '.$md->lech_goals.':'.$md->opponent_goals]) }}</x-ui.chip>
                        @if ($first = $this->scores->first())
                            <x-ui.chip variant="gold">{{ __('Points for the tip: :points of 3.', ['points' => $first->tip_points]) }}</x-ui.chip>
                        @endif
                    </div>
                @endif
            @endif
        </x-ui.card>

        @if ($md->isFilled() && $this->isOpen)
            <p class="text-center text-sm text-ui-muted">{{ __('Quick scores') }}</p>
            <div class="flex flex-wrap justify-center gap-2">
                @foreach ([[1, 0], [2, 0], [2, 1], [3, 1], [1, 1], [0, 0], [2, 2], [0, 1], [1, 2]] as [$quickLech, $quickOpp])
                    <button type="button" wire:click="pick({{ $quickLech }}, {{ $quickOpp }})" @class([
                        'ui-num rounded-xl px-3.5 py-2 text-lg font-bold transition active:scale-95',
                        'bg-club text-club-ink' => (int) $this->lech === $quickLech && (int) $this->opponent === $quickOpp,
                        'bg-ui-raised' => ! ((int) $this->lech === $quickLech && (int) $this->opponent === $quickOpp),
                    ])>{{ $quickLech }}:{{ $quickOpp }}</button>
                @endforeach
            </div>
        @endif

        <p class="text-center text-sm text-ui-muted">
            @if ($this->tip)
                {{ __('Saved tip: :score, last change :time.', ['score' => $this->tip->score(), 'time' => $this->tip->saved_at->translatedFormat('j F, H:i:s')]) }}
            @else
                {{ __('You have not tipped this matchday yet.') }}
            @endif
        </p>

        @if ($md->isFilled())
            @php $nextStep = isset($steps['questions']) ? 'questions' : 'players'; @endphp
            <x-ui.button wire:click="$set('step', '{{ $nextStep }}')">{{ __('Next: :step', ['step' => $steps[$nextStep]]) }}</x-ui.button>
        @endif
    @elseif ($step === 'questions')
        @include('partials.tip-questions')
        <x-ui.button wire:click="$set('step', 'players')">{{ __('Next: :step', ['step' => __('Squad picks')]) }}</x-ui.button>
    @elseif ($step === 'players')
        @if ($md->isFilled())
            <livewire:pages::home.designation-panels part="select" :matchday-id="$md->id" :key="'designation-select-' . $md->id" />
            <livewire:pages::home.designation-panels part="cards" :matchday-id="$md->id" :key="'designation-cards-' . $md->id" />
            <x-ui.button wire:click="$set('step', 'ticket')">{{ __('Next: :step', ['step' => __('Ticket')]) }}</x-ui.button>
        @endif
    @else
        {{-- Bilet: podsumowanie typu i składu. Typ zapisuje się sam przy każdej zmianie, „Skasuj bilet” tylko to potwierdza. --}}
        @php $ticket = $this->ticket; @endphp
        <section x-data="{ punched: false }" x-on:ticket-punched.window="punched = true"
            class="relative flex flex-col gap-2.5 rounded-[20px] border border-ui-line bg-ui-surface p-4 [mask:radial-gradient(circle_12px_at_0_62%,transparent_98%,#000)_left/51%_100%_no-repeat,radial-gradient(circle_12px_at_100%_62%,transparent_98%,#000)_right/51%_100%_no-repeat]">
            <div class="flex items-center gap-2">
                <img src="{{ asset(config('club.logo')) }}" alt="" class="size-7">
                <b class="font-display text-xl">{{ __('Your ticket') }}</b>
                <span class="ms-auto text-xs text-ui-muted">{{ __('Matchday :number', ['number' => $md->number]) }}</span>
            </div>
            <div class="ui-num text-center text-[40px] font-extrabold leading-none">
                {{ mb_strtoupper(config('club.short_name')) }} {{ $this->tip ? $this->tip->score() : '–:–' }} {{ mb_strtoupper((string) $md->opponent) }}
            </div>
            @if ($this->questions->isNotEmpty())
                <div class="flex justify-between gap-3 border-t border-dashed border-ui-line pt-2 text-sm">
                    <span class="text-ui-muted">{{ __('Questions') }}</span>
                    <span class="text-end font-semibold">{{ __(':answered of :total', ['answered' => (string) $this->myAnswers->count(), 'total' => (string) $this->questions->count()]) }} · ⚔ {{ $this->myAnswers->where('side', 'attack')->count() }} · 🛡 {{ $this->myAnswers->where('side', 'defense')->count() }}</span>
                </div>
            @endif
            @forelse ($ticket as $line)
                <div class="flex justify-between gap-3 border-t border-dashed border-ui-line pt-2 text-sm">
                    <span class="text-ui-muted">{{ $line['label'] }}</span>
                    <span class="text-end font-semibold">{{ $line['players'] ?: '–' }}</span>
                </div>
            @empty
                <div class="border-t border-dashed border-ui-line pt-2 text-sm text-ui-muted">{{ __('No players fielded.') }}</div>
            @endforelse
            <span x-show="punched || {{ $this->tip && !$this->isOpen ? 'true' : 'false' }}" x-cloak
                class="animate-ui-stamp pointer-events-none absolute bottom-14 end-4 rounded-lg border-[3px] border-good px-2.5 py-0.5 font-display text-xl font-extrabold tracking-wider text-good">{{ __('PUNCHED') }}</span>
        </section>

        @if ($this->isOpen && $this->tip)
            <x-ui.button wire:click="punch">{{ __('Punch the ticket') }}</x-ui.button>
        @endif

        @if ($league = $this->myLeague)
            @php $matchdaysByNumber = $this->matchdays->keyBy('number'); @endphp
            @include('partials.my-matches-path', [
                'title' => __('Your league matches') . ' · ' . ($league['competition']->name ?: $league['competition']->type->label()),
                'status' => $league['status'],
                'rounds' => \App\Models\Matchday::PER_SEASON,
                'perRound' => 1,
                'path' => $league['path'],
                'entryId' => $league['entry_id'],
                'eliminated' => null,
                'stageLabel' => fn (int $stage) => __('Matchday :number', ['number' => $stage]) . (($matchdaysByNumber[$stage] ?? null)?->isOpenForTips() ? ' •' : ''),
                'stageAction' => 'openMatchday',
                'stageExtra' => fn (int $stage) => match (true) {
                    !isset($matchdaysByNumber[$stage]) || blank($matchdaysByNumber[$stage]->opponent) => __('Match not assigned yet'),
                    $matchdaysByNumber[$stage]->lech_goals !== null => 'Lech ' . $matchdaysByNumber[$stage]->lech_goals . ':' . $matchdaysByNumber[$stage]->opponent_goals . ' ' . $matchdaysByNumber[$stage]->opponent,
                    default => $matchdaysByNumber[$stage]->opponent . ($matchdaysByNumber[$stage]->kickoff_at ? ', ' . $matchdaysByNumber[$stage]->kickoff_at->translatedFormat('j M H:i') : ''),
                },
                'current' => $this->number,
            ])
        @endif
    @endif

    @if ($md->isFilled() && $step === 'ticket')

        {{-- Premium: jak typują inni gracze (na żywo, także przed pierwszym gwizdkiem) --}}
        @if ($this->premium && $this->crowd)
            @php
                $crowd = $this->crowd;
                $crowdPct = fn (int $part) => $crowd['count'] > 0 ? (int) round($part / $crowd['count'] * 100) : 0;
                $crowdPlayers = \App\Support\MatchdayInsights::players($this->matchday);
                $crowdColors = ['league' => 'league_1', 'cup' => 'cup', 'champions' => 'champions', 'legends' => 'legends'];
            @endphp
            <div class="space-y-2 rounded-2xl border border-ui-line bg-ui-raised/50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-semibold uppercase tracking-wide text-ui-muted">
                    <span class="flex items-center gap-1"><x-ui.icon name="sparkles" variant="micro" />{{ __('How the others tip') }}</span>
                    <span class="tabular-nums">{{ trans_choice(':count tip|:count tips', $crowd['count'], ['count' => $crowd['count']]) }}</span>
                </div>
                @if ($crowd['count'] > 0)
                    <div class="flex h-6 overflow-hidden rounded-full text-[11px] font-bold text-white">
                        @foreach ([['lech', 'bg-lech-700', 'Lech'], ['draw', 'bg-zinc-400', __('Draw result')], ['opponent', 'bg-zinc-700', $this->matchday->opponent]] as [$crowdPart, $crowdColor, $crowdLabel])
                            @if ($crowd[$crowdPart] > 0)
                                <span class="{{ $crowdColor }} flex items-center justify-center" style="width: {{ $crowdPct($crowd[$crowdPart]) }}%" title="{{ $crowdLabel }}: {{ $crowd[$crowdPart] }}">{{ $crowdPct($crowd[$crowdPart]) }}%</span>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-ui-muted">
                        <span>Lech {{ $crowdPct($crowd['lech']) }}% · {{ __('Draw result') }} {{ $crowdPct($crowd['draw']) }}% · {{ $this->matchday->opponent }} {{ $crowdPct($crowd['opponent']) }}%</span>
                        <span class="flex flex-wrap gap-1">
                            {{ __('Most popular') }}:
                            @foreach ($crowd['popular'] as $popular)
                                <span class="rounded bg-ui-surface px-1.5 font-bold tabular-nums">{{ $popular['score'] }} ×{{ $popular['count'] }}</span>
                            @endforeach
                        </span>
                    </div>
                @endif

                {{-- Najczęściej wybierani zawodnicy: cztery bloki typów rozgrywek, po trzech zawodników --}}
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($crowdPlayers as $crowdSet => $crowdBlock)
                        <div class="comp-banner rounded-lg p-2" style="{{ \App\Support\CompetitionColors::style($crowdColors[$crowdSet]) }}">
                            <div class="relative z-10 flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wide">
                                <span class="truncate">{{ $crowdBlock['label'] }}</span>
                                <span class="shrink-0 font-semibold opacity-80" title="{{ __('Clubs with a line-up') }}">{{ $crowdBlock['clubs'] }}</span>
                            </div>
                            <ol class="relative z-10 mt-1.5 space-y-1">
                                @forelse ($crowdBlock['top'] as $crowdPlayer)
                                    <li class="flex items-center gap-1.5 rounded bg-white/90 px-1.5 py-0.5 text-[11px] text-zinc-900">
                                        <span class="w-3 shrink-0 text-[9px] font-semibold opacity-60">{{ $crowdPlayer['position'] }}</span>
                                        <span class="min-w-0 flex-1 truncate font-semibold">{{ $crowdPlayer['name'] }}</span>
                                        <span class="shrink-0 tabular-nums opacity-70">{{ $crowdPlayer['percent'] }}%</span>
                                    </li>
                                @empty
                                    <li class="text-[11px] opacity-80">{{ __('Nobody fielded yet.') }}</li>
                                @endforelse
                            </ol>
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif (!$this->premium)
            <x-premium-lock :title="__('How the others tip')"
                :text="__('With premium you see live how the other players tip this match.')" />
        @endif
    @endif

    @else
        @include('partials.tips-tabs')
    @endif
</div>
