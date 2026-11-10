<?php

use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\FinalStanding;
use App\Models\HallOfFameAward;
use App\Models\Season;
use App\Models\TeamLike;
use App\Models\User;
use App\Support\HallOfFame;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Strona zespołu (etap 14): punkty Hall of Fame i miejsce w rankingu wszech czasów, gablota z trofeami
 * oraz historia zakończonych sezonów (miejsca w rozgrywkach i zdobyte punkty).
 * W banerze licznik wyświetleń (oko, raz na sesję odwiedzającego, bez właściciela) i polubienia (niebieskie serduszko).
 */
new #[Layout('layouts::public')] class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->countView();
        \App\Support\Seo::description(__(':team in #LechTYPER: trophies, Hall of Fame points and season history.', ['team' => $user->team_name ?: $user->name]));
    }

    /** Wyświetlenie klubu: raz na sesję odwiedzającego; wejścia właściciela się nie liczą. */
    private function countView(): void
    {
        $key = 'team-viewed.'.$this->user->id;
        if (auth()->id() === $this->user->id || session()->has($key)) {
            return;
        }

        session()->put($key, true);
        // Bez zmiany updated_at użytkownika.
        DB::table('users')->where('id', $this->user->id)->increment('profile_views');
        $this->user->profile_views++;
    }

    #[Computed]
    public function likes(): int
    {
        return TeamLike::where('user_id', $this->user->id)->count();
    }

    #[Computed]
    public function likedByMe(): bool
    {
        return auth()->check() && TeamLike::where('user_id', $this->user->id)->where('liker_id', auth()->id())->exists();
    }

    /** Serduszko: polubienie albo jego cofnięcie (zalogowani, nie swój klub). */
    public function toggleLike(): void
    {
        abort_unless(auth()->check() && auth()->id() !== $this->user->id, 403);

        $deleted = TeamLike::where('user_id', $this->user->id)->where('liker_id', auth()->id())->delete();
        if (! $deleted) {
            TeamLike::create(['user_id' => $this->user->id, 'liker_id' => auth()->id()]);
        }

        unset($this->likes, $this->likedByMe);
    }

    public function render(): View
    {
        return $this->view()->title($this->user->team_name ?: $this->user->name);
    }

    /** Sezon do sekcji „Rozgrywki w sezonie”: aktywny albo zatwierdzony (przed startem), a bez nich ostatnio zakończony. */
    #[Computed]
    public function currentSeason(): ?Season
    {
        return Season::whereIn('status', [\App\Enums\SeasonStatus::Active->value, \App\Enums\SeasonStatus::Approved->value])->orderByDesc('number')->first()
            ?? Season::where('status', \App\Enums\SeasonStatus::Finished->value)->orderByDesc('number')->first();
    }

    /**
     * Liga, w której zespół gra w sezonie (z miejsca na liście, także gdy dołączył w trakcie sezonu i nie ma jeszcze meczów),
     * z linkiem do tabeli z wyróżnionym zespołem. Null: brak miejsca na liście sezonu.
     *
     * @return array{label: string, url: ?string}|null
     */
    #[Computed]
    public function currentLeague(): ?array
    {
        $season = $this->currentSeason;
        $team = $season ? \App\Models\SeasonTeam::where('season_id', $season->id)->where('user_id', $this->user->id)->first() : null;

        if (!$season || !$team) {
            return null;
        }

        $league = \App\Enums\League::forPosition((int) $team->position);
        $row = collect($this->seasonCompetitions)->first(fn ($r) => in_array($r['type'], [CompetitionType::League, CompetitionType::Swiss], true));

        return ['label' => $row['name'] ?? $league->label(), 'url' => $row['url'] ?? null];
    }

    /**
     * Rozgrywki zespołu w sezonie: miejsce w tabeli albo etap (puchar, Liga Legend) i link do wyników
     * z wyróżnionym zespołem (?team=), na kolejce jego ostatniego meczu.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function seasonCompetitions(): array
    {
        $season = $this->currentSeason;
        if (!$season) {
            return [];
        }

        return array_map(function (array $row) use ($season) {
            $lastMatch = end($row['matches']);
            $round = $row['eliminated_round'] ?? ($lastMatch ? $lastMatch['round'] : null);
            $params = ['season_slug' => 'sezon-' . $season->number, 'competition_slug' => $row['slug']];
            if ($round) {
                $params['round_slug'] = 'kolejka-' . $round;
            }
            $row['url'] = route('results', $params) . '?team=' . $row['team_id'];
            $row['out'] = (bool) $row['eliminated_round'];

            return $row;
        }, \App\Support\PlayerStats::competitions($this->user, $season));
    }

    /** Statystyki sezonu: swoje widzi każdy, cudze tylko premium (null = zablokowane albo brak sezonu). */
    #[Computed]
    public function seasonStats(): ?array
    {
        $viewer = auth()->user();
        $allowed = $viewer && ($viewer->id === $this->user->id || \App\Support\Premium::isActive($viewer));

        return $allowed && $this->currentSeason ? \App\Support\PlayerStats::season($this->user, $this->currentSeason) : null;
    }

    #[Computed]
    public function awards()
    {
        return HallOfFameAward::with('season:id,number')->where('user_id', $this->user->id)->get();
    }

    #[Computed]
    public function total(): float
    {
        return round((float) $this->awards->sum('points'), 1);
    }

    /** Miejsce w rankingu wszech czasów (null, gdy zespół nie ma jeszcze punktów). */
    #[Computed]
    public function rank(): ?int
    {
        if ($this->awards->isEmpty()) {
            return null;
        }

        $better = HallOfFameAward::query()
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('sum(points) > ?', [$this->total])
            ->select('user_id')
            ->get()
            ->count();

        return $better + 1;
    }

    /**
     * Gablota: trofea pogrupowane według rodzaju, z sezonami zdobycia.
     *
     * @return array<int, array{key: string, label: string, count: int, seasons: array<int, string>}>
     */
    #[Computed]
    public function cabinet(): array
    {
        $names = HallOfFame::trophies();

        return $this->awards->whereNotNull('trophy')
            ->groupBy('trophy')
            ->map(fn($rows, $key) => [
                'key' => $key,
                'label' => $names[$key] ?? $key,
                'count' => $rows->count(),
                'seasons' => $rows->map(fn($a) => $a->season?->roman_number)->filter()->sort()->values()->all(),
            ])
            // Kolejność według ważności trofeów (HallOfFame::trophies(): od Ligi Legend w dół).
            ->sortBy(fn($item) => array_search($item['key'], array_keys($names), true))
            ->values()
            ->all();
    }

    /**
     * Historia sezonów (App\Support\TeamHistory): rozgrywki, wynik, zmiana ligi, punkty Hall of Fame.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function history(): array
    {
        return \App\Support\TeamHistory::for($this->user->id, null, $this->awards);
    }

    #[Computed]
    public function icons(): array
    {
        return HallOfFame::iconUrls();
    }

}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <x-page-banner :eyebrow="__('Hall of Fame')" :title="$user->team_name ?: $user->name">
        <a href="{{ route('hall-of-fame') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-lech-100 hover:text-white hover:underline">
            <x-ui.icon name="arrow-left" variant="micro" /> {{ __('Full ranking') }}
        </a>

        {{-- Wyświetlenia i polubienia klubu. --}}
        @php
            $canLike = auth()->check() && auth()->id() !== $user->id;
        @endphp
        <div class="flex items-center gap-4 text-sm font-semibold">
            <span class="inline-flex items-center gap-1.5 text-lech-100" title="{{ __('Views') }}">
                <x-ui.icon name="eye" variant="mini" class="size-5" />
                <span class="tabular-nums">{{ number_format($user->profile_views, 0, ',', ' ') }}</span>
                <span class="sr-only">{{ __('Views') }}</span>
            </span>
            @if ($canLike)
                <button type="button" wire:click="toggleLike" aria-pressed="{{ $this->likedByMe ? 'true' : 'false' }}"
                    title="{{ $this->likedByMe ? __('Unlike') : __('Like this club') }}"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-2 py-0.5 text-sky-300 transition hover:scale-105 hover:bg-white/10">
                    <x-ui.icon name="heart" :variant="$this->likedByMe ? 'solid' : 'outline'" class="size-5" />
                    <span class="tabular-nums">{{ $this->likes }}</span>
                </button>
            @else
                <span class="inline-flex items-center gap-1.5 text-sky-300" title="{{ auth()->check() ? __('Likes') : __('Log in to like') }}">
                    <x-ui.icon name="heart" variant="solid" class="size-5" />
                    <span class="tabular-nums">{{ $this->likes }}</span>
                </span>
            @endif
        </div>

        <x-slot:aside>
            <div class="flex flex-wrap gap-6 rounded-xl bg-white/10 px-5 py-3">
                {{-- Aktualna liga zespołu (z listy sezonu), link do tabeli --}}
                <div class="text-right">
                    <p class="text-xs uppercase tracking-widest text-lech-200">{{ $this->currentSeason ? __('League') . ' · ' . $this->currentSeason->title : __('League') }}</p>
                    @if ($this->currentLeague)
                        @if ($this->currentLeague['url'])
                            <a href="{{ $this->currentLeague['url'] }}" wire:navigate class="text-2xl font-bold hover:underline">{{ $this->currentLeague['label'] }}</a>
                        @else
                            <p class="text-2xl font-bold">{{ $this->currentLeague['label'] }}</p>
                        @endif
                    @else
                        <p class="text-base font-semibold text-lech-100">{{ __('Not on the season list') }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-widest text-lech-200">{{ __('Hall of Fame points') }}</p>
                    <p class="text-2xl font-bold tabular-nums">{{ \App\Support\HallOfFame::format($this->total) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-widest text-lech-200">{{ __('All-time rank') }}</p>
                    <p class="text-2xl font-bold tabular-nums">{{ $this->rank ? $this->rank . '.' : '—' }}</p>
                </div>
            </div>
        </x-slot:aside>
    </x-page-banner>

    {{-- Rozgrywki w bieżącym sezonie: miejsce albo odpadnięcie, kliknięcie otwiera wyniki z wyróżnionym zespołem --}}
    @if (count($this->seasonCompetitions) > 0)
        <x-ui.card class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <x-ui.heading size="lg">{{ __('Competitions this season') }}</x-ui.heading>
                <x-ui.badge color="zinc">{{ $this->currentSeason->title }}</x-ui.badge>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->seasonCompetitions as $row)
                    <a href="{{ $row['url'] }}" wire:navigate wire:key="sc-{{ $loop->index }}"
                        class="{{ $row['out'] ? 'border-zinc-500 bg-ui-raised' : 'border-lech-200 bg-lech-50/60 hover:border-lech-400 dark:border-lech-800 dark:bg-lech-950/40' }} flex items-center gap-3 rounded-xl border p-3 text-sm transition hover:shadow-md">
                        @if (isset($this->icons[$row['trophy']]))
                            <img src="{{ $this->icons[$row['trophy']] }}" alt="" class="{{ $row['out'] ? 'opacity-40 grayscale' : '' }} size-10 shrink-0 object-contain">
                        @else
                            <x-ui.icon name="trophy" class="size-10 shrink-0 text-amber-500" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold">{{ $row['name'] }}</div>
                            <div class="{{ $row['out'] ? 'text-ui-muted' : 'text-club' }} flex items-center gap-1 text-xs font-medium">
                                @if ($row['out'])
                                    <x-ui.icon name="x-circle" variant="micro" />
                                @endif
                                <span class="truncate">{{ $row['status'] }}</span>
                            </div>
                        </div>
                        @if ($row['points'] !== null)
                            <span class="shrink-0 font-bold tabular-nums">{{ __(':points pts', ['points' => $row['points']]) }}</span>
                        @endif
                        <x-ui.icon name="chevron-right" variant="micro" class="shrink-0 text-zinc-400" />
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    {{-- Statystyki sezonu (jak zakładka Statystyki na /tips): swoje dla każdego, cudze w premium --}}
    @if ($this->currentSeason)
        <x-ui.card class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <x-ui.heading size="lg">{{ __('Statistics') }}</x-ui.heading>
                <x-ui.badge color="zinc">{{ $this->currentSeason->title }}</x-ui.badge>
            </div>
            @if ($this->seasonStats)
                @include('partials.player-stats-tiles', ['data' => $this->seasonStats, 'user' => $user])
            @else
                <x-premium-lock :title="__('Statistics')" :text="__('Your own statistics are free in My tips. With premium you also see the statistics of other players.')" />
            @endif
        </x-ui.card>
    @endif

    {{-- Gablota --}}
    <x-ui.card class="space-y-4">
        <x-ui.heading size="lg">{{ __('Trophy cabinet') }}</x-ui.heading>

        @if (count($this->cabinet) === 0)
            <x-ui.text>{{ __('No trophies yet.') }}</x-ui.text>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($this->cabinet as $item)
                    <div class="flex flex-col items-center gap-2 rounded-xl border border-ui-line p-4 text-center"
                        wire:key="cab-{{ $item['key'] }}">
                        <div class="relative">
                            @if (isset($this->icons[$item['key']]))
                                <img src="{{ $this->icons[$item['key']] }}" alt="" class="size-16 object-contain">
                            @else
                                <x-ui.icon name="trophy" class="size-16 text-amber-500" />
                            @endif
                            @if ($item['count'] > 1)
                                <span class="absolute -right-2 -top-1 rounded-full bg-amber-500 px-1.5 text-xs font-bold text-white">×{{ $item['count'] }}</span>
                            @endif
                        </div>
                        <span class="text-sm font-medium">{{ $item['label'] }}</span>
                        <span class="text-xs text-ui-muted">{{ implode(', ', $item['seasons']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    {{-- Historia sezonów --}}
    @include('partials.team-history', ['history' => $this->history])
</div>
