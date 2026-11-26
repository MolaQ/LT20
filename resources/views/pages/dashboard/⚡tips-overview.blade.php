<?php

use App\Enums\CompetitionType;
use App\Models\CompetitionEntry;
use App\Models\DesignatedPlayer;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Support\CompetitionColors;
use App\Support\Players;
use App\Support\Questions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Podgląd typów kolejki dla admina: kto już typował, a kto jeszcze nie.
 * Tylko odczyt (uprawnienie season-list), nic tu nie zmienia danych.
 */
new class extends Component {
    use WithPagination;

    #[Url(as: 'season', except: 0)]
    public int $seasonId = 0;

    #[Url(as: 'matchday', except: 1)]
    public int $number = 1;

    /** all | tipped | missing */
    #[Url(as: 'show', except: 'all')]
    public string $show = 'all';

    public function mount(): void
    {
        if (!Season::whereKey($this->seasonId)->exists()) {
            $this->seasonId = Season::current()?->id ?? (int) Season::orderByDesc('number')->value('id');
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('Tips overview'));
    }

    public function updatedSeasonId(): void
    {
        $this->number = 1;
        $this->resetPage();
    }

    public function updatedNumber(): void
    {
        $this->resetPage();
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function seasons()
    {
        return Season::orderByDesc('number')->get();
    }

    #[Computed]
    public function matchdays()
    {
        return Matchday::where('season_id', $this->seasonId)->orderBy('number')->get();
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return $this->matchdays->firstWhere('number', $this->number) ?? $this->matchdays->first();
    }

    /**
     * Gracze sezonu (na liście, z rolą i bez bana) – boty nie typują. Przy wybranej kolejce tylko ci, którzy byli
     * w grze przed jej pierwszym gwizdkiem (Players::expected).
     */
    private function playersQuery()
    {
        if ($this->matchday) {
            return Players::expected($this->matchday);
        }

        return SeasonTeam::query()
            ->where('season_id', $this->seasonId)
            ->whereNotNull('user_id')
            ->whereIn('user_id', Players::eligible()->select('id'));
    }

    /** Liczniki: ilu graczy powinno typować i ilu już typowało. */
    #[Computed]
    public function totals(): array
    {
        if (!$this->matchday) {
            return [0, 0];
        }

        $total = $this->playersQuery()->count();
        $tipped = $this->playersQuery()
            ->whereIn('user_id', Tip::where('matchday_id', $this->matchday->id)->select('user_id'))
            ->count();

        return [$total, $tipped];
    }

    #[Computed]
    public function players()
    {
        $query = $this->playersQuery()->with('user:id,name,team_name')->orderBy('position');

        if ($this->matchday) {
            $tipped = Tip::where('matchday_id', $this->matchday->id)->select('user_id');

            if ($this->show === 'tipped') {
                $query->whereIn('user_id', $tipped);
            } elseif ($this->show === 'missing') {
                $query->whereNotIn('user_id', $tipped);
            }
        }

        return $query->paginate(25);
    }

    /**
     * Szczegóły graczy z bieżącej strony: typ, rozgrywki, w których grają w tej kolejce, oraz w każdych z nich
     * pytania z bonusem ataku i obrony i liczba wystawionych zawodników.
     *
     * @return array<int, array{tip: ?Tip, sets: array<string, array{attack: int, defense: int, answered: int, players: int}>}>
     */
    #[Computed]
    public function details(): array
    {
        if (!$this->matchday) {
            return [];
        }

        $md = $this->matchday;
        $ids = $this->players->getCollection()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $tips = Tip::where('matchday_id', $md->id)->whereIn('user_id', $ids)->get()->keyBy('user_id');

        // Rozgrywki, w których gracz jeszcze gra w tej kolejce (jak PlayerCompetitions::types, tylko dla całej strony naraz).
        $plays = CompetitionEntry::query()
            ->join('competitions', 'competitions.id', '=', 'competition_entries.competition_id')
            ->join('season_teams', 'season_teams.id', '=', 'competition_entries.season_team_id')
            ->where('competitions.season_id', $md->season_id)
            ->whereIn('season_teams.user_id', $ids)
            ->where(fn ($q) => $q->whereNull('competition_entries.eliminated_round')->orWhere('competition_entries.eliminated_round', '>=', $md->number))
            ->toBase()
            ->get(['season_teams.user_id', 'competitions.type']);

        $answers = QuestionAnswer::whereIn('matchday_question_id', MatchdayQuestion::where('matchday_id', $md->id)->select('id'))
            ->whereIn('user_id', $ids)
            ->get(['user_id', 'score_set', 'side']);

        $designated = DesignatedPlayer::where('matchday_id', $md->id)
            ->whereIn('user_id', $ids)
            ->selectRaw('user_id, score_set, COUNT(*) as total')
            ->groupBy('user_id', 'score_set')
            ->toBase()
            ->get();

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['tip' => $tips->get($id), 'sets' => []];
        }
        foreach ($plays as $row) {
            $set = CompetitionType::from((string) $row->type)->scoreSet()->value;
            $out[(int) $row->user_id]['sets'][$set] = ['attack' => 0, 'defense' => 0, 'answered' => 0, 'players' => 0];
        }
        foreach ($answers as $answer) {
            if (isset($out[$answer->user_id]['sets'][$answer->score_set])) {
                $out[$answer->user_id]['sets'][$answer->score_set]['answered']++;
                $out[$answer->user_id]['sets'][$answer->score_set][$answer->side === 'defense' ? 'defense' : 'attack']++;
            }
        }
        foreach ($designated as $row) {
            if (isset($out[(int) $row->user_id]['sets'][(string) $row->score_set])) {
                $out[(int) $row->user_id]['sets'][(string) $row->score_set]['players'] = (int) $row->total;
            }
        }

        return $out;
    }

    /**
     * Kolumny rozgrywek (najwyżej cztery naraz): zestaw => nazwa, styl barw i liczba pytań w tej kolejce.
     *
     * @return array<string, array{label: string, style: string, questions: int}>
     */
    #[Computed]
    public function sets(): array
    {
        $colors = [CompetitionType::League->value => 'league_1', CompetitionType::Cup->value => 'cup', CompetitionType::Champions->value => 'champions', CompetitionType::Legends->value => 'legends'];
        $questions = $this->matchday ? Questions::for($this->matchday)->countBy('score_set') : collect();

        $out = [];
        foreach (Questions::sets() as $set) {
            $out[$set] = ['label' => Questions::setLabel($set), 'style' => CompetitionColors::style($colors[$set]), 'questions' => (int) $questions->get($set, 0)];
        }

        return $out;
    }

    /** Liczby w banerze: ilu ma ustawiony skład i ilu odpowiedziało na pytania (spośród graczy kolejki). */
    #[Computed]
    public function readiness(): array
    {
        if (!$this->matchday) {
            return ['squads' => 0, 'answers' => 0];
        }

        $users = $this->playersQuery()->select('user_id');

        return [
            'squads' => DesignatedPlayer::where('matchday_id', $this->matchday->id)->whereIn('user_id', $users)->distinct()->count('user_id'),
            'answers' => QuestionAnswer::whereIn('matchday_question_id', MatchdayQuestion::where('matchday_id', $this->matchday->id)->select('id'))
                ->whereIn('user_id', $this->playersQuery()->select('user_id'))->distinct()->count('user_id'),
        ];
    }
}; ?>

@php
    $md = $this->matchday;
    [$total, $done] = $this->totals;
    $percent = $total > 0 ? (int) round($done * 100 / $total) : 0;
@endphp

<div class="flex w-full flex-col gap-6">
    <x-admin-hero :title="__('Tips overview')" :subtitle="__('Who has already tipped the selected matchday and who has not.')" icon="check-badge"
        :eyebrow="$md ? __('Matchday :number', ['number' => $md->number]) . ' · ' . $md->fixture : null">
        @if ($md)
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-admin-hero-stat :label="__('Players who tipped')" :value="$done . ' / ' . $total" :percent="$percent" />
                <x-admin-hero-stat :label="__('Still missing')" :value="max(0, $total - $done)" :tone="$total - $done > 0 ? 'warn' : 'good'" />
                <x-admin-hero-stat :label="__('Squads set')" :value="$this->readiness['squads'] . ' / ' . $total" :percent="$total > 0 ? $this->readiness['squads'] * 100 / $total : 0" />
                <x-admin-hero-stat :label="__('Answered the questions')" :value="$this->readiness['answers'] . ' / ' . $total" :percent="$total > 0 ? $this->readiness['answers'] * 100 / $total : 0" />
            </div>
            <div class="text-xs text-white/60">
                {{ $md->kickoff_at ? __('Kickoff: :date', ['date' => $md->kickoff_at->translatedFormat('l, j F, H:i')]) : '' }}
                · {{ __('Counted: players who were in the game before the kickoff.') }}
            </div>
        @endif
    </x-admin-hero>

    {{-- Wybór sezonu, kolejki i filtra --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-ui-line bg-ui-surface p-4">
        <div class="flex flex-wrap items-end gap-3">
            @if ($this->seasons->isNotEmpty())
                <div class="w-full sm:w-52">
                    <x-ui.select wire:model.live="seasonId" :label="__('Season')">
                        @foreach ($this->seasons as $option)
                            <x-ui.select.option :value="$option->id">{{ $option->title }} ({{ $option->status->label() }})</x-ui.select.option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif
            <x-ui.segmented wire:model.live="show" class="w-full sm:ms-auto sm:w-96"
                :options="['all' => __('Everyone'), 'tipped' => __('Already tipped'), 'missing' => __('Not tipped yet')]" />
        </div>
        @if ($this->matchdays->isNotEmpty())
            <x-ui.filter-pills model="number" :value="$number" :label="__('Matchday')" class="w-full"
                :options="$this->matchdays->mapWithKeys(fn ($o) => [$o->number => $o->number.'. '.(filled($o->opponent) ? $o->opponent : '–')])->all()" />
        @endif
    </div>

    @if (!$md)
        <x-ui.card>
            <x-ui.text>{{ __('No matchdays yet.') }}</x-ui.text>
        </x-ui.card>
    @else
        @php $sets = $this->sets; @endphp
        <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-sm">
                    <thead>
                        <tr class="border-b border-ui-line text-left text-[11px] font-bold uppercase tracking-wider text-ui-muted">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">{{ __('Team') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Tip') }}</th>
                            @foreach ($sets as $set)
                                <th class="px-2 py-2">
                                    <span class="comp-chip inline-flex max-w-full items-center truncate rounded-lg px-2 py-1 text-[10px] normal-case tracking-normal" style="{{ $set['style'] }}" title="{{ $set['label'] }}">{{ $set['label'] }}</span>
                                </th>
                            @endforeach
                            <th class="px-4 py-3">{{ __('Saved') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ui-line">
                        @forelse ($this->players as $team)
                            @php $detail = $this->details[$team->user_id] ?? ['tip' => null, 'sets' => []]; @endphp
                            <tr wire:key="tips-row-{{ $team->id }}" @class(['transition hover:bg-ui-raised/50', 'bg-amber-50/40 dark:bg-amber-500/5' => !$detail['tip']])>
                                <td class="px-4 py-3 tabular-nums text-ui-muted">{{ $team->position }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $team->name }}</div>
                                    <div class="text-xs text-ui-muted">{{ $team->league->label() }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($detail['tip'])
                                        <span class="inline-block min-w-12 rounded-lg bg-good/15 px-2 py-1 font-display text-base font-bold tabular-nums text-good">{{ $detail['tip']->score() }}</span>
                                    @else
                                        <span class="inline-block min-w-12 rounded-lg bg-ui-raised px-2 py-1 font-bold text-ui-muted">—</span>
                                    @endif
                                </td>
                                @foreach ($sets as $key => $set)
                                    @php $cell = $detail['sets'][$key] ?? null; @endphp
                                    <td class="px-2 py-3">
                                        @if ($cell === null)
                                            <span class="text-ui-muted/50">—</span>
                                        @else
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-1">
                                                    @foreach (['attack' => ['swords', 'var(--q-attack)', __('Attack')], 'defense' => ['shield', 'var(--q-defense)', __('Defence')]] as $side => [$icon, $color, $title])
                                                        <span @class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold', 'text-white' => $cell[$side] > 0, 'bg-ui-raised text-ui-muted' => $cell[$side] === 0])
                                                            @if ($cell[$side] > 0) style="background: {{ $color }}" @endif title="{{ $title }}">
                                                            <x-ui.icon :name="$icon" variant="micro" class="size-3.5" />{{ $cell[$side] }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                                <div class="flex items-center gap-2 text-[11px] text-ui-muted">
                                                    <span title="{{ __('Fielded players') }}" class="inline-flex items-center gap-0.5"><x-ui.icon name="user-group" variant="micro" class="size-3" />{{ $cell['players'] }}</span>
                                                    @if ($set['questions'] > 0)
                                                        <span title="{{ __('Questions') }}">{{ __(':done/:all q.', ['done' => $cell['answered'], 'all' => $set['questions']]) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-xs text-ui-muted">{{ $detail['tip']?->saved_at->translatedFormat('j F, H:i:s') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + count($sets) }}" class="px-4 py-8 text-center text-ui-muted">{{ __('No players match your filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($this->players->hasPages())
                <div class="border-t border-ui-line px-4 py-3">{{ $this->players->links() }}</div>
            @endif
        </section>
    @endif
</div>
