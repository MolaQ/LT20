<?php

use App\Enums\Permission;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionTemplate;
use App\Models\Season;
use App\Support\Audit;
use App\Support\Questions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pytania kolejki (przebudowa 2.0): admin klika pytania z banku (najwyżej 5 na kolejkę), a po meczu
 * zaznacza prawidłową odpowiedź (tak / nie / unieważnione). Bank pytań: dopisywanie i wyłączanie szablonów.
 * Podgląd: season-list, zmiany: season-edit. Zmiana odpowiedzi po rozliczeniu wymaga przeliczenia kolejki w Wynikach.
 */
new class extends Component {
    #[Url(as: 'kolejka', except: 0)]
    public int $number = 0;

    /** Nowe pytanie do banku (jedyne pole tekstowe na tej stronie). */
    public string $newText = '';

    public function mount(): void
    {
        if (!$this->matchdays->firstWhere('number', $this->number)) {
            $this->number = $this->matchdays->first(fn ($m) => $m->isOpenForTips())?->number
                ?? $this->matchdays->first()?->number ?? 0;
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('Questions'));
    }

    #[Computed]
    public function matchdays()
    {
        $season = Season::current();

        return $season ? Matchday::where('season_id', $season->id)->orderBy('number')->get() : collect();
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return $this->matchdays->firstWhere('number', $this->number);
    }

    #[Computed]
    public function questions()
    {
        return $this->matchday ? Questions::for($this->matchday)->loadCount(['answers' => fn ($q) => $q->select(\Illuminate\Support\Facades\DB::raw('count(distinct user_id)'))]) : collect();
    }

    #[Computed]
    public function templates()
    {
        return QuestionTemplate::orderByDesc('is_active')->orderBy('times_used')->orderBy('id')->get();
    }

    private function authorizeEdit(): void
    {
        abort_unless(auth()->user()?->can(Permission::SeasonEdit->value), 403);
    }

    /** Klik w pytanie z banku: dodaje je do kolejki albo zdejmuje (najwyżej 5). */
    public function toggleTemplate(int $templateId): void
    {
        $this->authorizeEdit();

        if (!$this->matchday) {
            return;
        }

        $ids = $this->questions->pluck('question_template_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $ids = in_array($templateId, $ids, true) ? array_values(array_diff($ids, [$templateId])) : [...$ids, $templateId];

        if (count($ids) > Questions::PER_MATCHDAY) {
            $this->dispatch('ui-toast', variant: 'warning', text: __('A matchday has at most :count questions.', ['count' => Questions::PER_MATCHDAY]));

            return;
        }

        Questions::setForMatchday($this->matchday, $ids);
        Audit::log('questions.set', null, [], ['matchday' => $this->matchday->number, 'templates' => $ids], __('Matchday :number', ['number' => $this->matchday->number]));
        unset($this->questions, $this->templates);
    }

    /** Prawidłowa odpowiedź po meczu: 'yes', 'no', 'void' albo '' (jeszcze nie wiadomo). */
    public function settle(int $questionId, string $value): void
    {
        $this->authorizeEdit();

        $question = MatchdayQuestion::where('matchday_id', $this->matchday?->id)->findOrFail($questionId);
        $old = ['correct' => $question->correct, 'voided' => $question->voided];
        $question->update([
            'correct' => match ($value) { 'yes' => true, 'no' => false, default => null },
            'voided' => $value === 'void',
        ]);
        Audit::log('questions.settled', null, $old, ['correct' => $question->correct, 'voided' => $question->voided], $question->text);
        unset($this->questions);
    }

    public function addTemplate(): void
    {
        $this->authorizeEdit();

        $data = $this->validate(['newText' => ['required', 'string', 'min:8', 'max:160']]);
        QuestionTemplate::create(['text' => trim($data['newText'])]);
        $this->newText = '';
        unset($this->templates);
    }

    public function toggleActive(int $templateId): void
    {
        $this->authorizeEdit();

        $template = QuestionTemplate::findOrFail($templateId);
        $template->update(['is_active' => !$template->is_active]);
        unset($this->templates);
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div>
        <x-ui.heading size="xl">{{ __('Questions') }}</x-ui.heading>
        <x-ui.text>{{ __('Pick up to :count yes/no questions for the matchday with a click. After the match, mark the right answer.', ['count' => \App\Support\Questions::PER_MATCHDAY]) }}</x-ui.text>
    </div>

    @if ($this->matchdays->isEmpty())
        <x-ui.callout icon="information-circle">{{ __('No matchdays yet.') }}</x-ui.callout>
    @else
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach ($this->matchdays as $option)
                <button type="button" wire:click="$set('number', {{ $option->number }})" wire:key="qmd-{{ $option->id }}"
                    class="{{ $option->number === $number ? 'bg-club text-club-ink' : 'bg-ui-raised' }} shrink-0 rounded-xl px-3 py-2 text-sm font-semibold">
                    {{ __('Matchday :number', ['number' => $option->number]) }}
                    @if ($option->isOpenForTips())<span class="ms-1 inline-block size-2 rounded-full bg-green-500"></span>@endif
                </button>
            @endforeach
        </div>

        @can(\App\Enums\Permission::SeasonEdit->value)
            @php($canEdit = true)
        @else
            @php($canEdit = false)
        @endcan

        <x-ui.card class="space-y-3">
            <x-ui.heading>{{ __('Questions of matchday :number', ['number' => $number]) }} ({{ $this->questions->count() }}/{{ \App\Support\Questions::PER_MATCHDAY }})</x-ui.heading>
            @forelse ($this->questions as $question)
                @php($state = $question->voided ? 'void' : ($question->correct === null ? '' : ($question->correct ? 'yes' : 'no')))
                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-ui-line p-3 " wire:key="mq-{{ $question->id }}">
                    <span class="w-6 font-bold text-zinc-400">{{ $question->position }}.</span>
                    <span class="min-w-0 flex-1 font-medium">{{ $question->text }}</span>
                    <span class="text-xs text-ui-muted">{{ trans_choice(':count answer|:count answers', $question->answers_count, ['count' => $question->answers_count]) }}</span>
                    <div class="flex gap-1">
                        @foreach (['yes' => __('Yes'), 'no' => __('No'), 'void' => __('Voided'), '' => __('Not yet')] as $value => $label)
                            <button type="button" wire:click="settle({{ $question->id }}, '{{ $value }}')" @disabled(!$canEdit)
                                class="{{ $state === $value ? 'bg-club text-club-ink' : 'bg-ui-raised' }} rounded-lg px-2.5 py-1 text-xs font-bold">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-ui.text>{{ __('No questions in this matchday. Click questions from the bank below.') }}</x-ui.text>
            @endforelse
            @if ($this->matchday?->status === \App\Enums\MatchdayStatus::Played)
                <x-ui.callout icon="arrow-path" variant="warning">{{ __('The matchday is already settled: after changing answers recalculate it in Results.') }}</x-ui.callout>
            @endif
        </x-ui.card>

        <x-ui.card class="space-y-3">
            <x-ui.heading>{{ __('Question bank') }}</x-ui.heading>
            <div class="flex flex-wrap gap-2">
                @php($chosen = $this->questions->pluck('question_template_id')->all())
                @foreach ($this->templates->where('is_active', true) as $template)
                    <button type="button" wire:click="toggleTemplate({{ $template->id }})" wire:key="qt-{{ $template->id }}" @disabled(!$canEdit)
                        class="{{ in_array($template->id, $chosen) ? 'border-lech-700 bg-club text-club-ink' : 'border-zinc-300 hover:border-lech-500 dark:border-zinc-600' }} rounded-xl border px-3 py-2 text-start text-sm">
                        {{ $template->text }}
                        <span class="ms-1 text-xs opacity-60">×{{ $template->times_used }}</span>
                    </button>
                @endforeach
            </div>

            @if ($canEdit)
                <form wire:submit="addTemplate" class="flex gap-2">
                    <x-ui.input wire:model="newText" :placeholder="__('New yes/no question, e.g. Will Lech score first?')" class="flex-1" />
                    <x-ui.btn type="submit" variant="primary">{{ __('Add') }}</x-ui.btn>
                </form>

                <details class="text-sm">
                    <summary class="cursor-pointer text-ui-muted">{{ __('Turn questions in the bank on or off') }}</summary>
                    <div class="mt-2 space-y-1">
                        @foreach ($this->templates as $template)
                            <label class="flex items-center gap-2" wire:key="qta-{{ $template->id }}">
                                <input type="checkbox" @checked($template->is_active) wire:click="toggleActive({{ $template->id }})">
                                <span class="{{ $template->is_active ? '' : 'text-zinc-400 line-through' }}">{{ $template->text }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endif
        </x-ui.card>
    @endif
</div>
