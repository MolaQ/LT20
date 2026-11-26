<?php

use App\Enums\CompetitionType;
use App\Models\CompetitionEntry;
use App\Models\Matchday;
use App\Models\SeasonTeam;
use App\Support\MatchSheet;
use App\Support\PlayerCompetitions;
use App\Support\Squad;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Desygnowanie zawodników na /tips (zakładka Typowanie). Dwie części tego samego komponentu (`part`), zsynchronizowane zdarzeniami:
 *  - 'select' (obok tablicy typu, 3/4 szerokości): wybór typu rozgrywek, do którego dodajemy (kafelki z licznikiem),
 *    dostępni zawodnicy według doświadczenia w klubie gracza (filtr B / O / P / N, wystawieni w barwach swojego typu),
 *  - 'cards' (pod spodem, cała szerokość): karty typów rozgrywek z wystawionymi, po rozliczeniu protokoły meczów
 *    (Support\MatchSheet) z legendą, niżej odpoczywający (kolor według godzin do końca odpoczynku).
 * Klik w zawodnika: dodaje do wybranego typu, przenosi z innego typu albo zdejmuje (zapis od razu). Zasady: Support\Squad.
 */
new class extends Component {
    #[Locked]
    public int $matchdayId = 0;

    /** Część: 'select' (wybór zawodników) albo 'cards' (karty typów, protokoły, odpoczywający). */
    #[Locked]
    public string $part = 'select';

    /** Czy część 'select' pokazuje własny wybór rozgrywek (na /tips wybór rozgrywek jest nad składem i pytaniami). */
    #[Locked]
    public bool $chooser = true;

    /** Typ rozgrywek, do którego dodajemy kliknięciem (wartość CompetitionType). */
    public string $active = '';

    public function mount(): void
    {
        // Rozgrywki podane przez stronę (np. wybrane na /tips) albo pierwsze z rozgrywek gracza.
        if (!in_array(CompetitionType::tryFrom($this->active), $this->sets, true)) {
            $this->active = ($this->sets[0] ?? null)?->value ?? '';
        }
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return Matchday::find($this->matchdayId);
    }

    #[Computed]
    public function isOpen(): bool
    {
        return (bool) $this->matchday?->isOpenForTips() && $this->matchday->isReady();
    }

    /** @return array<int, CompetitionType> */
    #[Computed]
    public function sets(): array
    {
        $md = $this->matchday;

        return $md && auth()->id() ? PlayerCompetitions::types(auth()->id(), $md->season_id, $md->number) : [];
    }

    /** Kadra do wyboru według doświadczenia zawodnika w klubie gracza (remis: pozycja i numer). */
    #[Computed]
    public function squad()
    {
        $experience = Squad::clubExperience(auth()->id(), null);
        // Forma: doświadczenie z ostatnich 10 meczów Lecha (taka sama we wszystkich klubach).
        $form = \App\Support\MatchReport::formTotals();

        return Squad::available()->sortByDesc(fn ($p) => $experience[$p->id] ?? 0)->values()
            ->each(fn ($p) => $p->setAttribute('club_experience', $experience[$p->id] ?? 0.0)->setAttribute('lech_form', $form[$p->id] ?? 0.0));
    }

    /** Protokoły meczów rozliczonej kolejki: typ rozgrywek => mecze. */
    #[Computed]
    public function sheets(): array
    {
        $user = auth()->user();

        return $user && $this->matchday && $this->part === 'cards' ? MatchSheet::forUser($user, $this->matchday) : [];
    }

    /** @return array<string, array<int, int>> typ rozgrywek => id zawodników */
    #[Computed]
    public function picks(): array
    {
        return $this->matchday ? Squad::picks(auth()->id(), null, $this->matchday) : [];
    }

    /** @return array<int, int> id zawodnika => wolny od (timestamp) */
    #[Computed]
    public function locks(): array
    {
        return $this->matchday ? Squad::locks(auth()->id(), null, $this->matchday) : [];
    }

    /** @return array<int, true> zmęczeni: w regeneracji, ale koniec blisko, więc można ich wystawić (regeneracja po meczu dłuższa) */
    #[Computed]
    public function tired(): array
    {
        $matchday = $this->matchday;

        return $matchday ? array_map(fn () => true, array_filter($this->locks, fn (int $freeAt) => Squad::isTired($freeAt, $matchday))) : [];
    }

    /**
     * Barwy kart (klucz CompetitionColors): liga gracza, Puchar Polski, jego puchar europejski, Liga Legend.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function setColors(): array
    {
        return $this->matchday ? PlayerCompetitions::colors(auth()->id(), $this->matchday->season_id) : PlayerCompetitions::colors(0, 0);
    }

    /**
     * Nazwy rozgrywek gracza w kartach typów (np. „Ekstraklasa”, „Liga Mistrzów”); bez wpisu zostaje nazwa typu.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function setNames(): array
    {
        $names = [];
        $teamId = $this->matchday ? SeasonTeam::where('season_id', $this->matchday->season_id)->where('user_id', auth()->id())->value('id') : null;

        if ($teamId) {
            foreach (CompetitionEntry::with('competition')->where('season_team_id', $teamId)->get() as $entry) {
                if ($entry->competition) {
                    $names[$entry->competition->type->scoreSet()->value] = $entry->competition->name ?: $entry->competition->type->label();
                }
            }
        }

        return $names;
    }

    /** Wybór typu rozgrywek (kafelek albo karta), wspólny dla obu części. */
    #[On('designation-active')]
    public function selectSet(string $set): void
    {
        if (CompetitionType::tryFrom($set) && in_array(CompetitionType::from($set), $this->sets, true) && $this->active !== $set) {
            $this->active = $set;
            $this->dispatch('designation-active', set: $set);
        }
    }

    /** Skład zmieniony w drugiej części. */
    #[On('designation-changed')]
    public function refreshPicks(): void
    {
        unset($this->picks, $this->squad);
    }

    /** Klik w zawodnika: do wybranego typu rozgrywek (albo podanego, np. zdjęcie z karty typu). */
    public function toggle(int $playerId, string $set = ''): void
    {
        $type = CompetitionType::tryFrom($set !== '' ? $set : $this->active);
        $user = auth()->user();

        if (!$type || !$user || !$this->matchday) {
            return;
        }

        try {
            Squad::toggle($user, $this->matchday, $type, $playerId);
        } catch (DomainException $e) {
            $this->dispatch('ui-toast', variant: 'danger', text: $e->getMessage());
        }

        unset($this->picks);
        $this->dispatch('designation-changed');
    }
}; ?>

<div>
    @if ($this->matchday && count($this->sets) > 0)
        @php
            $max = \App\Support\Designation::value('max_players');
            $picks = $this->picks;
            $locks = $this->locks;
            $tired = $this->tired;
            $tiredFactor = rtrim(rtrim(number_format(\App\Support\Designation::number('tired_rest_factor'), 1, ',', ''), '0'), ',');
            $tiredTitle = __('Almost recovered: he can play, but after this match his recovery takes :factor times longer.', ['factor' => $tiredFactor]);
            $squad = $this->squad;
            $colors = $this->setColors;
            $sets = collect($this->sets)->keyBy(fn ($set) => $set->value);
            // Gdzie wystawiony jest zawodnik: id => typ rozgrywek (wartość).
            $pickedIn = [];
            foreach ($picks as $setValue => $ids) {
                foreach ($ids as $id) {
                    $pickedIn[$id] = $setValue;
                }
            }
            $open = $this->isOpen;
            $active = $sets->get($this->active);
            $fmt = fn ($v) => \App\Support\ClubStats::experience($v);
        @endphp

        @if ($part === 'select')
            @php
                // Zmęczeni (koniec regeneracji blisko) też są do wyboru, z ikoną plastra.
                $available = $squad->filter(fn ($p) => !isset($locks[$p->id]) || isset($tired[$p->id]));
            @endphp
            <div class="space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <x-ui.heading size="lg">{{ __('Your line-ups') }}</x-ui.heading>
                        <x-ui.text class="text-sm">
                            {{ __('Field up to :count Lech players in each competition type. Their goals and assists add attack points, clean sheets take points from the rival.', ['count' => $max]) }}
                        </x-ui.text>
                    </div>
                    @if (!$open)
                        <x-ui.badge color="zinc" icon="lock-closed">{{ __('Line-ups closed') }}</x-ui.badge>
                    @endif
                </div>

                {{-- Wybór typu rozgrywek, do którego trafia kliknięty zawodnik (albo tylko nazwa, gdy wybór jest wyżej na stronie) --}}
                @if ($open && !$chooser && $active)
                    <div class="comp-chip inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold" style="{{ \App\Support\CompetitionColors::style($colors[$this->active] ?? 'league_1') }}">
                        {{ __('Adding to: :set', ['set' => $active->scoreSetLabel()]) }}
                        <span class="tabular-nums">{{ count($picks[$this->active] ?? []) }}/{{ $max }}</span>
                    </div>
                @elseif ($open)
                    <div class="space-y-1.5">
                        <div class="text-xs font-medium text-ui-muted">{{ __('Add players to') }}</div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($sets as $setValue => $set)
                                @php
                                    $isActive = $setValue === $this->active;
                                    $count = count($picks[$setValue] ?? []);
                                @endphp
                                <button type="button" wire:click="selectSet('{{ $setValue }}')" wire:key="pick-set-{{ $setValue }}"
                                    style="{{ \App\Support\CompetitionColors::style($colors[$setValue] ?? 'league_1') }}"
                                    class="{{ $isActive ? 'comp-chip shadow-sm ring-1 ring-club/60' : 'comp-chip opacity-60 hover:opacity-100' }} flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left transition">
                                    <span class="truncate text-xs font-medium">{{ $set->scoreSetLabel() }}</span>
                                    <span class="shrink-0 text-xs font-semibold tabular-nums">{{ $count }}/{{ $max }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Dostępni zawodnicy według doświadczenia w klubie: barwa = typ rozgrywek, w którym są wystawieni --}}
                <section class="space-y-2 rounded-2xl border border-ui-line bg-ui-surface p-4" x-data="{ pos: '' }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-ui.heading>{{ __('Available players') }} <span class="font-normal text-ui-muted">({{ $available->count() }})</span></x-ui.heading>
                        <div class="flex gap-1">
                            <button type="button" x-on:click="pos = ''" x-bind:class="pos === '' ? 'bg-club text-club-ink' : 'bg-ui-raised text-ui-muted '"
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium">{{ __('All') }}</button>
                            @foreach (\App\Enums\PlayerPosition::cases() as $position)
                                <button type="button" x-on:click="pos = '{{ $position->value }}'" title="{{ $position->label() }}"
                                    x-bind:class="pos === '{{ $position->value }}' ? 'bg-club text-club-ink' : 'bg-ui-raised text-ui-muted '"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium">{{ $position->short() }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-1 sm:grid-cols-3">
                        @foreach ($available as $player)
                            @php
                                $in = $pickedIn[$player->id] ?? null;
                                $inSet = $in ? $sets->get($in) : null;
                                $full = $active && $in !== $this->active && count($picks[$this->active] ?? []) >= $max;
                                $title = ($inSet ? $inSet->scoreSetLabel() . '. ' : '') . (!$open ? '' : ($in === $this->active ? __('Remove') : ($full ? __('You can field at most :count players here.', ['count' => $max]) : ($in ? __('Move to :set', ['set' => $active?->scoreSetLabel()]) : __('Add to :set', ['set' => $active?->scoreSetLabel()])))));
                            @endphp
                            <button type="button" wire:click="toggle({{ $player->id }})" wire:key="avail-{{ $player->id }}" @disabled(!$open || $full)
                                x-show="pos === '' || pos === '{{ $player->position->value }}'" title="{{ $title }}"
                                @if ($in) style="{{ \App\Support\CompetitionColors::style($colors[$in] ?? 'league_1') }}" @endif
                                class="{{ $in ? 'comp-chip shadow-sm' : 'border border-ui-line hover:border-club/40 hover:bg-ui-raised/60' }} {{ $full && !$in ? 'opacity-50' : '' }} flex min-w-0 flex-col gap-0.5 rounded-lg px-2.5 py-1.5 text-left transition disabled:cursor-default">
                                {{-- Dwie linie (19.11.2026): na telefonie w jednej linii nazwisko miało jedną literę. Góra: nazwisko na całą szerokość i znacznik; dół: numer, pozycja, forma, doświadczenie. --}}
                                <span class="flex w-full min-w-0 items-center gap-1">
                                    <span class="min-w-0 flex-1 truncate text-[13px] font-semibold">
                                        {{ $player->name }}
                                        @if ($player->is_academy)
                                            <x-ui.icon name="academic-cap" variant="micro" class="inline opacity-70" />
                                        @endif
                                    </span>
                                    @if (isset($tired[$player->id]))
                                        <x-plaster :title="$tiredTitle" />
                                    @endif
                                    @if ($inSet)
                                        <x-ui.icon name="check-circle" variant="micro" class="size-4 shrink-0" />
                                    @endif
                                </span>
                                {{-- Stałe szerokości kolumn: numer 1 i 99 wyglądają tak samo, płomień i gwiazdka zawsze w tym samym miejscu. --}}
                                <span class="flex w-full min-w-0 items-center gap-1 overflow-hidden text-[11px] tabular-nums">
                                    <span class="w-4 shrink-0 text-right font-semibold opacity-70">{{ $player->number }}</span>
                                    <span class="w-3 shrink-0 font-semibold uppercase opacity-70" title="{{ $player->position->label() }}">{{ $player->position->short() }}</span>
                                    <span class="flex w-8 shrink-0 items-center gap-0.5 {{ $in ? 'opacity-90' : 'text-amber-600 dark:text-amber-400' }}" title="{{ __('Form: experience from the last :count Lech matches', ['count' => \App\Support\MatchReport::FORM_MATCHES]) }}"><x-ui.icon name="fire" variant="micro" class="size-3" />{{ $fmt($player->lech_form) }}</span>
                                    <span class="flex items-center gap-0.5 opacity-75" title="{{ __('Experience in your club') }}"><x-ui.icon name="star" variant="micro" class="size-3" />{{ $fmt($player->club_experience) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>
                {{-- Plaster (26.11.2026): zawodnik niezregenerowany, wystawienie grozi kontuzją, czyli dłuższą regeneracją po meczu. --}}
                @if ($tired !== [])
                    <div class="flex items-start gap-2 rounded-xl border border-amber-300/60 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                        <x-plaster class="mt-px size-4" />
                        <span>{{ __('Plaster: the player has not fully recovered yet. You can field him, but he risks an injury: after this match his return to full fitness takes :factor times longer.', ['factor' => $tiredFactor]) }}</span>
                    </div>
                @endif
                <x-ui.text class="text-xs">{{ __('Under a player: the flame shows his form (experience from the last :count Lech matches), the star his experience in your club. A player can be in only one competition type per matchday: clicking him with another type selected moves him there.', ['count' => \App\Support\MatchReport::FORM_MATCHES]) }}</x-ui.text>
            </div>
        @else
            @php
                $resting = $squad->filter(fn ($p) => isset($locks[$p->id]))->sortBy(fn ($p) => $locks[$p->id]);
                $sheets = $this->sheets;
            @endphp
            <div class="space-y-4">
                {{-- Karty typów rozgrywek: wystawieni (klik w kartę wybiera typ, klik w zawodnika zdejmuje), po rozliczeniu protokoły meczów --}}
                {{-- Dwie kolumny: na komputerze panel stoi w połowie ekranu typowania, obok biletu --}}
                <div class="grid items-start gap-3 sm:grid-cols-2">
                    @foreach ($sets as $setValue => $set)
                        @php
                            $mine = $squad->whereIn('id', $picks[$setValue] ?? [])->sortBy(fn ($p) => array_search($p->position, \App\Enums\PlayerPosition::cases(), true));
                            $isActive = $open && $setValue === $this->active;
                            $setSheets = $sheets[$setValue] ?? [];
                        @endphp
                        <section data-share-target wire:key="set-{{ $setValue }}" style="{{ \App\Support\CompetitionColors::style($colors[$setValue] ?? 'league_1') }}"
                            @if ($open) wire:click="selectSet('{{ $setValue }}')" @endif
                            class="comp-banner {{ $isActive ? 'ring-2 ring-lech-600 ring-offset-2 ring-offset-white dark:ring-offset-zinc-900' : '' }} {{ $open ? 'cursor-pointer' : '' }} min-w-0 rounded-2xl p-2.5">
                            <header class="relative z-10 flex items-center justify-between gap-2 px-0.5">
                                <h2 class="min-w-0 text-xs font-semibold uppercase leading-tight tracking-wider">{{ $this->setNames[$setValue] ?? $set->scoreSetLabel() }}</h2>
                                <span class="flex shrink-0 items-center gap-1.5">
                                    <span class="comp-badge rounded-md px-1.5 py-0.5 text-[11px] font-bold tabular-nums">{{ $mine->count() }}/{{ $max }}</span>
                                    {{-- Udostępnianie bez przełączania rozgrywek (kliknięcie kafelka wybiera rozgrywki) --}}
                                    <span @click.stop><x-share-image :title="$this->setNames[$setValue] ?? $set->scoreSetLabel()" class="!size-6" /></span>
                                </span>
                            </header>

                            @if ($setSheets === [])
                                <div class="relative z-10 mt-2 flex min-h-8 flex-col gap-1">
                                    @forelse ($mine as $player)
                                        @php
                                            $forfeit = isset($locks[$player->id]) && !isset($tired[$player->id]);
                                        @endphp
                                        <button type="button" wire:click.stop="toggle({{ $player->id }}, '{{ $setValue }}')" @disabled(!$open)
                                            class="{{ $forfeit ? 'bg-red-600 text-white' : 'bg-white/90 text-zinc-900' }} flex min-w-0 items-center gap-1.5 rounded-md px-2 py-1 text-left text-xs font-semibold shadow-sm"
                                            title="{{ $forfeit ? __('This player rests now: the pick does not count.') : ($open ? __('Remove') : '') }}">
                                            <span class="w-4 shrink-0 text-[10px] opacity-60">{{ $player->position->short() }}</span>
                                            <span class="min-w-0 flex-1 truncate">{{ $player->name }}</span>
                                            @if (isset($tired[$player->id]))
                                                <x-plaster :title="$tiredTitle" />
                                            @endif
                                            <span class="shrink-0 text-[10px] tabular-nums opacity-60" title="{{ __('Experience in your club') }}">{{ $fmt($player->club_experience) }}</span>
                                            @if ($forfeit)
                                                <x-ui.icon name="no-symbol" variant="micro" />
                                            @elseif ($open)
                                                <x-ui.icon name="x-mark" variant="micro" class="opacity-50" />
                                            @endif
                                        </button>
                                    @empty
                                        <span class="text-xs opacity-80">{{ $open ? __('Nobody fielded yet. Choose this type and click players above.') : __('Nobody was fielded.') }}</span>
                                    @endforelse
                                </div>
                            @else
                                <div class="relative z-10 mt-2 space-y-2">
                                    @foreach ($setSheets as $sheet)
                                        @include('partials.match-sheet', ['sheet' => $sheet, 'showCompetition' => false])
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>

                {{-- Legenda protokołów --}}
                @if ($sheets !== [])
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl border border-ui-line bg-ui-surface px-3 py-2 text-xs text-ui-muted">
                        <span class="flex items-center gap-1.5"><x-ball /> {{ __('Attack point (goal, assist)') }}</span>
                        <span class="flex items-center gap-1.5"><x-ball own /> {{ __('Own goal: a point for the rival') }}</span>
                        <span class="flex items-center gap-1.5"><x-fist /> {{ __('Clean sheet: takes a point from the rival') }}</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-3.5 w-2.5 rounded-[2px] bg-yellow-400"></span><span class="inline-block h-3.5 w-2.5 rounded-[2px] bg-red-500"></span> {{ __('Cards') }}</span>
                        <span class="flex items-center gap-1.5"><span class="font-bold">{{ __('Exp.') }}</span> {{ __('experience in the club after the match') }}</span>
                        <span class="flex items-center gap-1.5 text-zinc-400">{{ __('Grey name: did not play in the Lech match (fielding still gives 0.5 experience)') }}</span>
                        <span class="flex items-center gap-1.5">{{ __('Liga Legend: each duel that takes place counts as one appearance') }}</span>
                    </div>
                @endif

                {{-- Odpoczywający: kolor według godzin do końca odpoczynku --}}
                @if ($resting->isNotEmpty())
                    <section class="space-y-2 rounded-2xl border border-ui-line bg-ui-raised/50 p-4">
                        <x-ui.heading>{{ __('Resting players') }} <span class="font-normal text-ui-muted">({{ $resting->count() }})</span></x-ui.heading>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($resting as $player)
                                @php
                                    $freeAt = $locks[$player->id];
                                    $in = $pickedIn[$player->id] ?? null;
                                @endphp
                                <div class="flex max-w-full items-center gap-1.5 rounded-md border border-ui-line bg-ui-surface px-2 py-1 text-xs text-ui-muted" wire:key="rest-{{ $player->id }}">
                                    {{-- Z lewej, przed pozycją: plaster (zmęczony, do wyboru) albo czerwony krzyż (niedostępny) --}}
                                    @if (isset($tired[$player->id]))
                                        <x-plaster :title="$tiredTitle" class="size-3.5" />
                                    @else
                                        <x-injury :title="__('Unavailable')" />
                                    @endif
                                    <span class="w-4 shrink-0 text-[10px] font-semibold uppercase">{{ $player->position->short() }}</span>
                                    <span class="min-w-0 truncate">{{ $player->name }}</span>
                                    @if ($in && $open && !isset($tired[$player->id]))
                                        {{-- Wystawiony, a potem odpoczywający (raport wpisany później): wybór przepada, można zdjąć. --}}
                                        <button type="button" wire:click="toggle({{ $player->id }}, '{{ $in }}')" class="shrink-0 rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white"
                                            title="{{ __('This player rests now: the pick does not count.') }}">{{ __('Remove') }}</button>
                                    @endif
                                    <span class="{{ \App\Support\Squad::lockColor(\App\Support\Squad::hoursLeft($freeAt)) }} shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold tabular-nums"
                                        title="{{ __(':count h left', ['count' => \App\Support\Squad::hoursLeft($freeAt)]) }}">
                                        {{ __('available from :date', ['date' => \Illuminate\Support\Carbon::createFromTimestamp($freeAt, config('app.timezone'))->format('d.m, H:i')]) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ui-muted">
                            @foreach (\App\Support\Squad::REST_LEGEND as $upTo => $sample)
                                <span class="flex items-center gap-1.5">
                                    <span class="{{ \App\Support\Squad::lockColor($sample) }} inline-block h-3 w-5 rounded"></span>
                                    {{ __('up to :count h', ['count' => $upTo]) }}
                                </span>
                            @endforeach
                        </div>
                    </section>
                @endif

                <x-ui.text class="text-xs">{{ __('A fielded player who played needs recovery in your club, counted from the kickoff: from :min h for part of a half to :max h for the whole match with extra time.', ['min' => \App\Support\Designation::value('rest_hours_1'), 'max' => \App\Support\Designation::value('rest_hours_8')]) }} <a href="{{ route('howto') }}#recovery" wire:navigate class="font-semibold text-club hover:underline">{{ __('How recovery works') }}</a></x-ui.text>
            </div>
        @endif
    @endif
</div>
