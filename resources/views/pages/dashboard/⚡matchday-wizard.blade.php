<?php

use App\Actions\Results\SaveMatchdayResult;
use App\Enums\Permission;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Tip;
use App\Support\MatchdayWizard;
use App\Support\Questions;
use App\Support\RecalcProgress;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kreator kolejki (przebudowa 2.0): jedna strona prowadzi admina krok po kroku przez kolejkę:
 * rywal i termin, pytania, typowanie, wynik z protokołem, odpowiedzi na pytania, przeliczenie (w tle z paskiem).
 * Kroki prowadzą do istniejących stron, a przeliczenie można uruchomić od razu tutaj. Stan kroków: MatchdayWizard.
 */
new class extends Component {
    #[Url(as: 'kolejka', except: 0)]
    public int $number = 0;

    public bool $recalcRunning = false;

    public function mount(): void
    {
        if (!$this->matchdays->firstWhere('number', $this->number)) {
            $this->number = MatchdayWizard::pick($this->matchdays)?->number ?? 0;
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('Matchday wizard'));
    }

    #[Computed]
    public function season(): ?Season
    {
        return Season::current();
    }

    #[Computed]
    public function matchdays()
    {
        return $this->season ? Matchday::where('season_id', $this->season->id)->orderBy('number')->get() : collect();
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return $this->matchdays->firstWhere('number', $this->number);
    }

    #[Computed]
    public function steps(): array
    {
        if (!$this->matchday) {
            return [];
        }

        return MatchdayWizard::steps($this->matchday, Questions::for($this->matchday), Tip::where('matchday_id', $this->matchday->id)->count());
    }

    #[Computed]
    public function recalcProgress(): ?array
    {
        return $this->matchday ? RecalcProgress::get($this->matchday->id) : null;
    }

    public function choose(int $number): void
    {
        $this->number = $number;
        $this->recalcRunning = false;
        unset($this->matchday, $this->steps, $this->recalcProgress);
    }

    public function recalculate(): void
    {
        abort_unless(auth()->user()?->can(Permission::SeasonEdit->value), 403);

        if (!$this->matchday) {
            return;
        }

        try {
            SaveMatchdayResult::ensureEditable($this->matchday);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->recalcRunning = true;
        RecalcProgress::start($this->matchday->id);
        $this->checkRecalc();
    }

    /** Odpytywane (wire:poll), dopóki przeliczenie trwa. */
    public function checkRecalc(): void
    {
        unset($this->recalcProgress);
        $progress = $this->recalcProgress;

        if (!$this->recalcRunning || !$progress || !in_array($progress['status'], ['done', 'failed'], true)) {
            return;
        }

        $this->recalcRunning = false;
        RecalcProgress::forget($this->matchday->id);
        unset($this->matchdays, $this->matchday, $this->steps, $this->recalcProgress);
        Flux::toast(variant: $progress['status'] === 'done' ? 'success' : 'danger', text: $progress['message']);
    }

    /** Opis i przycisk kroku. */
    public function stepInfo(string $key): array
    {
        $md = $this->matchday;

        return match ($key) {
            'match' => [__('Opponent and kick-off'), __('Assign a Lech match to the matchday.'), __('Open matchdays'), route('dashboard.matchdays')],
            'questions' => [__('Questions'), __('Click up to 5 questions from the bank. You can skip this step.'), __('Pick questions'), route('dashboard.questions', ['kolejka' => $md?->number])],
            'tips' => [__('Tipping'), __('Players tip until kick-off. Here you see how many have tipped.'), __('Tips overview'), route('dashboard.tips', ['season' => $md?->season_id, 'matchday' => $md?->number])],
            'result' => [__('Result and match report'), __('Enter the score, the line-up and the events of the match.'), __('Open the report'), route('dashboard.results', ['season' => $md?->season_id, 'matchday' => $md?->number])],
            'answers' => [__('Question answers'), __('Mark the right answer to each question after the match.'), __('Settle questions'), route('dashboard.questions', ['kolejka' => $md?->number])],
            'recalc' => [__('Recalculation'), __('Counts points, league tables and the next rounds.'), null, null],
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Matchday wizard') }}</flux:heading>
        <flux:text>{{ __('Everything a matchday needs, step by step. Click the step to do it.') }}</flux:text>
    </div>

    @if ($this->matchdays->isEmpty())
        <flux:callout icon="information-circle">{{ __('No matchdays yet.') }}</flux:callout>
    @else
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach ($this->matchdays as $option)
                <button type="button" wire:click="choose({{ $option->number }})" wire:key="wmd-{{ $option->id }}"
                    class="{{ $option->number === $number ? 'bg-lech-700 text-white' : 'bg-zinc-100 dark:bg-zinc-800' }} shrink-0 rounded-xl px-3 py-2 text-sm font-semibold">
                    {{ $option->number }}
                    @if ($option->status === \App\Enums\MatchdayStatus::Played)
                        <span class="ms-0.5 text-green-500">✓</span>
                    @elseif ($option->isOpenForTips())
                        <span class="ms-1 inline-block size-2 rounded-full bg-green-500"></span>
                    @endif
                </button>
            @endforeach
        </div>

        @php($done = collect($this->steps)->where('state', 'done')->count())
        @php($total = count($this->steps))
        <div class="flex items-center gap-3">
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div class="h-full rounded-full bg-lech-600 transition-all duration-700" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></div>
            </div>
            <span class="text-sm font-bold tabular-nums">{{ $done }}/{{ $total }}</span>
        </div>

        <ol class="flex flex-col gap-3">
            @foreach ($this->steps as $i => $step)
                @php([$title, $help, $action, $href] = $this->stepInfo($step['key']))
                <li wire:key="ws-{{ $step['key'] }}" @class([
                    'flex gap-3 rounded-2xl border p-4 transition',
                    'border-lech-600 bg-lech-50 shadow-sm dark:bg-lech-950/40' => $step['state'] === 'current',
                    'border-zinc-200 dark:border-zinc-700' => $step['state'] !== 'current',
                    'opacity-70' => $step['state'] === 'todo',
                ])>
                    <span @class([
                        'grid size-9 shrink-0 place-items-center rounded-full text-base font-extrabold',
                        'bg-green-600 text-white' => $step['state'] === 'done',
                        'bg-lech-700 text-white' => $step['state'] === 'current',
                        'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => in_array($step['state'], ['todo', 'optional'], true),
                    ])>{{ $step['state'] === 'done' ? '✓' : $i + 1 }}</span>

                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold">{{ $title }}</span>
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold dark:bg-zinc-800">{{ $step['note'] }}</span>
                            @if ($step['state'] === 'optional')
                                <span class="text-xs text-zinc-500">{{ __('optional') }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-zinc-500">{{ $help }}</p>

                        @if ($step['key'] === 'recalc')
                            @if ($recalcRunning && $this->recalcProgress)
                                <div wire:poll.750ms="checkRecalc" class="space-y-1">
                                    <div class="h-3 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                        <div class="h-full rounded-full bg-lech-600 transition-all duration-500" style="width: {{ $this->recalcProgress['percent'] }}%"></div>
                                    </div>
                                    <p class="text-xs text-zinc-500">{{ $this->recalcProgress['label'] }} · {{ $this->recalcProgress['percent'] }}%</p>
                                </div>
                            @elseif ($this->matchday?->lech_goals !== null)
                                @can(\App\Enums\Permission::SeasonEdit->value)
                                    <flux:button wire:click="recalculate" variant="{{ $step['state'] === 'current' ? 'primary' : 'filled' }}" icon="arrow-path">
                                        {{ $step['state'] === 'done' ? __('Recalculate again') : __('Recalculate now') }}
                                    </flux:button>
                                @endcan
                            @endif
                        @elseif ($href)
                            <flux:button :href="$href" wire:navigate size="sm" variant="{{ $step['state'] === 'current' ? 'primary' : 'filled' }}" icon-trailing="arrow-right">
                                {{ $action }}
                            </flux:button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        @if (collect($this->steps)->every(fn ($s) => in_array($s['state'], ['done', 'optional'], true)))
            <flux:callout icon="check-circle" variant="success">{{ __('Matchday :number is complete.', ['number' => $number]) }}</flux:callout>
        @endif
    @endif
</div>
