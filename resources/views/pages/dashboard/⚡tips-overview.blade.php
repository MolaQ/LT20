<?php

use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Support\Players;
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

    /** Gracze sezonu (na liście, z rolą i bez bana) – boty nie typują. */
    private function playersQuery()
    {
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

    /** Typy graczy z bieżącej strony: user_id => [Tip]. */
    #[Computed]
    public function details(): array
    {
        if (!$this->matchday) {
            return [];
        }

        $ids = $this->players->getCollection()->pluck('user_id')->all();
        $tips = Tip::where('matchday_id', $this->matchday->id)->whereIn('user_id', $ids)->get()->keyBy('user_id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['tip' => $tips->get($id)];
        }

        return $out;
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <x-ui.heading size="xl" level="1">{{ __('Tips overview') }}</x-ui.heading>
            <x-ui.text>{{ __('Who has already tipped the selected matchday and who has not.') }}</x-ui.text>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            @if ($this->seasons->isNotEmpty())
                <div class="w-full sm:w-48">
                    <x-ui.select wire:model.live="seasonId" :label="__('Season')">
                        @foreach ($this->seasons as $option)
                            <x-ui.select.option :value="$option->id">{{ $option->title }} ({{ $option->status->label() }})
                            </x-ui.select.option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif

            @if ($this->matchdays->isNotEmpty())
                <div class="w-full sm:w-64">
                    <x-ui.select wire:model.live="number" :label="__('Matchday')">
                        @foreach ($this->matchdays as $option)
                            <x-ui.select.option :value="$option->number">
                                {{ $option->number }}. {{ filled($option->opponent) ? $option->fixture : __('not set yet') }}
                            </x-ui.select.option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif

            <div class="w-full sm:w-44">
                <x-ui.select wire:model.live="show" :label="__('Show')">
                    <x-ui.select.option value="all">{{ __('Everyone') }}</x-ui.select.option>
                    <x-ui.select.option value="tipped">{{ __('Already tipped') }}</x-ui.select.option>
                    <x-ui.select.option value="missing">{{ __('Not tipped yet') }}</x-ui.select.option>
                </x-ui.select>
            </div>
        </div>
    </div>

    @if (!$this->matchday)
        <x-ui.card>
            <x-ui.text>{{ __('No matchdays yet.') }}</x-ui.text>
        </x-ui.card>
    @else
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.badge color="green">
            {{ __('Tipped: :done of :total', ['done' => $this->totals[1], 'total' => $this->totals[0]]) }}
        </x-ui.badge>
        <x-ui.text size="sm">{{ $this->matchday->fixture }}</x-ui.text>
    </div>

    <x-ui.table :paginate="$this->players">
        <x-ui.table.columns>
            <x-ui.table.column>{{ __('Place') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Team') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Tip') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Saved') }}</x-ui.table.column>
        </x-ui.table.columns>

        <x-ui.table.rows>
            @forelse ($this->players as $team)
            @php
                $detail = $this->details[$team->user_id] ?? ['tip' => null];
            @endphp
            <x-ui.table.row :key="$team->id">
                <x-ui.table.cell>{{ $team->position }}</x-ui.table.cell>
                <x-ui.table.cell>{{ $team->name }}</x-ui.table.cell>
                <x-ui.table.cell>
                    @if ($detail['tip'])
                        <x-ui.badge color="green">{{ $detail['tip']->score() }}</x-ui.badge>
                    @else
                        <x-ui.badge color="zinc">—</x-ui.badge>
                    @endif
                </x-ui.table.cell>
                <x-ui.table.cell>{{ $detail['tip']?->saved_at->translatedFormat('j F, H:i:s') ?? '—' }}
                </x-ui.table.cell>
            </x-ui.table.row>
            @empty
            <x-ui.table.row>
                <x-ui.table.cell colspan="4">{{ __('No players match your filters.') }}</x-ui.table.cell>
            </x-ui.table.row>
            @endforelse
        </x-ui.table.rows>
    </x-ui.table>
    @endif
</div>
