<?php

use App\Enums\Permission;
use App\Enums\SeasonStatus;
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
 * Pytania kolejki (przebudowa 2.0): każdy typ rozgrywek ma osobny zestaw (zakładki); admin klika pytania z banku
 * (najwyżej 5 w zestawie, jedno pytanie z banku tylko w jednym zestawie kolejki), a po meczu
 * zaznacza prawidłową odpowiedź (tak / nie / unieważnione). Bank pytań: dopisywanie i wyłączanie szablonów.
 * Podgląd: season-list, zmiany: season-edit. Zmiana odpowiedzi po rozliczeniu wymaga przeliczenia kolejki w Wynikach.
 * Pytania można układać także w sezonie w przygotowaniu (szkic, zatwierdzony), zanim wystartuje,
 * a treść pytań z banku poprawiać (zmiana trafia też do pytań kolejek, na które nikt jeszcze nie odpowiedział):
 * ołówkiem przy pytaniu w zestawie, przy kafelku banku albo na liście „Popraw bank pytań”, zawsze w tym samym miejscu.
 */
new class extends Component {
    /** Sezon: trwający albo w przygotowaniu (pytania można ułożyć przed startem). */
    #[Url(as: 'sezon', except: 0)]
    public int $seasonNumber = 0;

    #[Url(as: 'kolejka', except: 0)]
    public int $number = 0;

    /** Zestaw pytań: typ rozgrywek (liga, puchar, puchary europejskie, Liga Legend). */
    #[Url(as: 'zestaw', except: 'league')]
    public string $set = 'league';

    /** Pytanie z banku w trakcie poprawiania i jego nowa treść. */
    public ?int $editingId = null;

    /** Gdzie trwa poprawka: 'set' (zestaw kolejki), 'bank' (kafelki banku) albo 'list' (lista „Popraw bank pytań”). */
    public string $editingWhere = 'list';

    public string $editText = '';

    /** Nowe pytanie do banku (jedyne pole tekstowe na tej stronie). */
    public string $newText = '';

    public function mount(): void
    {
        if (!$this->seasons->firstWhere('number', $this->seasonNumber)) {
            $this->seasonNumber = Season::current()?->number ?? $this->seasons->first()?->number ?? 0;
        }

        if (!in_array($this->set, Questions::sets(), true)) {
            $this->set = Questions::sets()[0];
        }

        $this->pickMatchday();
    }

    public function updatedSet(): void
    {
        if (!in_array($this->set, Questions::sets(), true)) {
            $this->set = Questions::sets()[0];
        }
        unset($this->questions);
    }

    public function updatedSeasonNumber(): void
    {
        unset($this->season, $this->matchdays, $this->matchday, $this->allQuestions, $this->questions);
        $this->number = 0;
        $this->pickMatchday();
    }

    /** Wybrana kolejka albo pierwsza otwarta do typowania, albo pierwsza w sezonie. */
    private function pickMatchday(): void
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

    /** Sezony do wyboru: trwający i w przygotowaniu. */
    #[Computed]
    public function seasons()
    {
        return Season::whereIn('status', [SeasonStatus::Active->value, SeasonStatus::Approved->value, SeasonStatus::Draft->value])
            ->orderBy('number')->get();
    }

    #[Computed]
    public function season(): ?Season
    {
        return $this->seasons->firstWhere('number', $this->seasonNumber);
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

    /** Wszystkie pytania kolejki (wszystkie zestawy) z liczbą graczy, którzy odpowiedzieli. */
    #[Computed]
    public function allQuestions()
    {
        return $this->matchday ? Questions::for($this->matchday)->loadCount(['answers' => fn ($q) => $q->select(\Illuminate\Support\Facades\DB::raw('count(distinct user_id)'))]) : collect();
    }

    /** Pytania wybranego zestawu. */
    #[Computed]
    public function questions()
    {
        return $this->allQuestions->where('score_set', $this->set)->values();
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

    /** Klik w pytanie z banku: dodaje je do zestawu albo zdejmuje (najwyżej 5, bez powtórek między zestawami). */
    public function toggleTemplate(int $templateId): void
    {
        $this->authorizeEdit();

        if (!$this->matchday) {
            return;
        }

        $ids = $this->questions->pluck('question_template_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $removing = in_array($templateId, $ids, true);

        if (!$removing && $this->allQuestions->where('score_set', '!=', $this->set)->contains('question_template_id', $templateId)) {
            $this->dispatch('ui-toast', variant: 'warning', text: __('This question is already in another set of this matchday.'));

            return;
        }

        $ids = $removing ? array_values(array_diff($ids, [$templateId])) : [...$ids, $templateId];

        if (count($ids) > Questions::PER_SET) {
            $this->dispatch('ui-toast', variant: 'warning', text: __('A set has at most :count questions.', ['count' => Questions::PER_SET]));

            return;
        }

        Questions::setForMatchday($this->matchday, $this->set, $ids);
        Audit::log('questions.set', null, [], ['matchday' => $this->matchday->number, 'set' => $this->set, 'templates' => $ids], __('Matchday :number', ['number' => $this->matchday->number]));
        unset($this->allQuestions, $this->questions, $this->templates);
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
        unset($this->allQuestions, $this->questions);
    }

    public function addTemplate(): void
    {
        $this->authorizeEdit();

        $data = $this->validate(['newText' => ['required', 'string', 'min:8', 'max:160']]);
        QuestionTemplate::create(['text' => trim($data['newText'])]);
        $this->newText = '';
        unset($this->templates);
    }

    public function editTemplate(int $templateId, string $where = 'list'): void
    {
        $this->authorizeEdit();

        $this->editingId = $templateId;
        $this->editingWhere = in_array($where, ['set', 'bank', 'list'], true) ? $where : 'list';
        $this->editText = (string) QuestionTemplate::findOrFail($templateId)->text;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editText = '';
    }

    /** Nowa treść pytania z banku; trafia też do pytań kolejek bez odpowiedzi graczy i bez rozstrzygnięcia. */
    public function saveTemplate(): void
    {
        $this->authorizeEdit();

        $data = $this->validate(['editText' => ['required', 'string', 'min:8', 'max:160']]);
        $template = QuestionTemplate::findOrFail($this->editingId);
        $old = $template->text;
        $text = trim($data['editText']);
        $template->update(['text' => $text]);

        MatchdayQuestion::where('question_template_id', $template->id)
            ->whereNull('correct')->where('voided', false)
            ->whereDoesntHave('answers')
            ->update(['text' => $text]);

        Audit::log('questions.template_edited', null, ['text' => $old], ['text' => $text], $text);
        $this->cancelEdit();
        unset($this->templates, $this->allQuestions, $this->questions);
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
        <x-ui.text>{{ __('Every competition has its own set of up to :count yes/no questions. A bank question can be used in only one set of a matchday. After the match, mark the right answer.', ['count' => \App\Support\Questions::PER_SET]) }}</x-ui.text>
    </div>

    {{-- Sezon trwający albo w przygotowaniu: pytania można ułożyć przed startem sezonu --}}
    @if ($this->seasons->count() > 1)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->seasons as $seasonOption)
                <button type="button" wire:click="$set('seasonNumber', {{ $seasonOption->number }})" wire:key="qs-{{ $seasonOption->id }}"
                    class="{{ $seasonOption->number === $seasonNumber ? 'bg-club text-club-ink' : 'bg-ui-raised' }} rounded-xl px-3 py-2 text-sm font-semibold">
                    {{ $seasonOption->title }}
                    @if ($seasonOption->status !== \App\Enums\SeasonStatus::Active)
                        <span class="ms-1 text-xs font-normal opacity-80">· {{ __('in preparation') }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

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

        {{-- Zestawy pytań: jeden na każdy typ rozgrywek, w zakładce stan zestawu --}}
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach (\App\Support\Questions::sets() as $setOption)
                @php($setCount = $this->allQuestions->where('score_set', $setOption)->count())
                <button type="button" wire:click="$set('set', '{{ $setOption }}')" wire:key="qset-{{ $setOption }}"
                    class="{{ $setOption === $set ? 'bg-club text-club-ink shadow-sm' : 'bg-ui-raised hover:bg-ui-raised/70' }} flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-start text-sm font-semibold">
                    <span class="truncate">{{ \App\Support\Questions::setLabel($setOption) }}</span>
                    <span class="{{ $setCount === \App\Support\Questions::PER_SET ? '' : 'opacity-70' }} shrink-0 text-xs tabular-nums">{{ $setCount }}/{{ \App\Support\Questions::PER_SET }}</span>
                </button>
            @endforeach
        </div>

        <x-ui.card class="space-y-3">
            <x-ui.heading>{{ __('Matchday :number', ['number' => $number]) }} · {{ \App\Support\Questions::setLabel($set) }} ({{ $this->questions->count() }}/{{ \App\Support\Questions::PER_SET }})</x-ui.heading>
            @forelse ($this->questions as $question)
                @php($state = $question->voided ? 'void' : ($question->correct === null ? '' : ($question->correct ? 'yes' : 'no')))
                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-ui-line p-3 " wire:key="mq-{{ $question->id }}">
                    <span class="w-6 font-bold text-zinc-400">{{ $question->position }}.</span>
                    @if ($editingId !== null && $editingWhere === 'set' && $editingId === $question->question_template_id)
                        <div class="min-w-0 flex-1">@include('partials.question-edit-form')</div>
                    @else
                        <span class="min-w-0 flex-1 font-medium">{{ $question->text }}</span>
                        @if ($canEdit && $question->question_template_id)
                            <button type="button" wire:click="editTemplate({{ $question->question_template_id }}, 'set')" title="{{ __('Edit the question') }}" aria-label="{{ __('Edit the question') }}"
                                class="grid size-7 shrink-0 place-items-center rounded-lg text-ui-muted transition hover:bg-ui-raised hover:text-ui-ink"><x-ui.icon name="pencil-square" variant="micro" /></button>
                        @endif
                    @endif
                    <span class="text-xs text-ui-muted">{{ trans_choice(':count answer|:count answers', $question->answers_count, ['count' => $question->answers_count]) }}</span>
                    <div class="flex gap-1">
                        @foreach (['yes' => __('Yes'), 'no' => __('No'), 'void' => __('Voided'), '' => __('Not yet')] as $value => $label)
                            <button type="button" wire:click="settle({{ $question->id }}, '{{ $value }}')" @disabled(!$canEdit)
                                class="{{ $state === $value ? 'bg-club text-club-ink' : 'bg-ui-raised' }} rounded-lg px-2.5 py-1 text-xs font-bold">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-ui.text>{{ __('No questions in this set. Click questions from the bank below.') }}</x-ui.text>
            @endforelse
            @if ($this->matchday?->status === \App\Enums\MatchdayStatus::Played)
                <x-ui.callout icon="arrow-path" variant="warning">{{ __('The matchday is already settled: after changing answers recalculate it in Results.') }}</x-ui.callout>
            @endif
        </x-ui.card>

        <x-ui.card class="space-y-3">
            <x-ui.heading>{{ __('Question bank') }}</x-ui.heading>
            <div class="flex flex-wrap gap-2">
                @php($chosen = $this->questions->pluck('question_template_id')->all())
                @php($elsewhere = $this->allQuestions->where('score_set', '!=', $set)->pluck('score_set', 'question_template_id'))
                @foreach ($this->templates->where('is_active', true) as $template)
                    @php($otherSet = $elsewhere->get($template->id))
                    @php($picked = in_array($template->id, $chosen))
                    @if ($editingId === $template->id && $editingWhere === 'bank')
                        <div class="basis-full" wire:key="qt-edit-{{ $template->id }}">@include('partials.question-edit-form')</div>
                    @else
                        {{-- Kafelek: klik dodaje do zestawu albo zdejmuje, ołówek obok poprawia treść w miejscu --}}
                        <span class="group inline-flex" wire:key="qt-{{ $template->id }}">
                            <button type="button" wire:click="toggleTemplate({{ $template->id }})" @disabled(!$canEdit || $otherSet)
                                class="{{ $picked ? 'border-lech-700 bg-club text-club-ink' : ($otherSet ? 'cursor-not-allowed border-dashed border-ui-line opacity-50' : 'border-zinc-300 hover:border-lech-500 dark:border-zinc-600') }} {{ $canEdit ? 'rounded-s-xl border-e-0' : 'rounded-xl' }} border py-2 ps-3 pe-2 text-start text-sm transition">
                                {{ $template->text }}
                                @if ($otherSet)
                                    <span class="ms-1 text-xs">· {{ \App\Support\Questions::setLabel($otherSet) }}</span>
                                @else
                                    <span class="ms-1 text-xs opacity-60">×{{ $template->times_used }}</span>
                                @endif
                            </button>
                            @if ($canEdit)
                                <button type="button" wire:click="editTemplate({{ $template->id }}, 'bank')" title="{{ __('Edit the question') }}" aria-label="{{ __('Edit the question') }}"
                                    class="{{ $picked ? 'border-lech-700 bg-club text-club-ink' : ($otherSet ? 'border-dashed border-ui-line' : 'border-zinc-300 dark:border-zinc-600') }} grid place-items-center rounded-e-xl border border-s-0 px-2 opacity-60 transition hover:opacity-100">
                                    <x-ui.icon name="pencil-square" variant="micro" />
                                </button>
                            @endif
                        </span>
                    @endif
                @endforeach
            </div>

            @if ($canEdit)
                <form wire:submit="addTemplate" class="flex gap-2">
                    <x-ui.input wire:model="newText" :placeholder="__('New yes/no question, e.g. Will Lech score first?')" class="flex-1" />
                    <x-ui.btn type="submit" variant="primary">{{ __('Add') }}</x-ui.btn>
                </form>

                <details class="text-sm" @if ($editingId && $editingWhere === 'list') open @endif>
                    <summary class="cursor-pointer text-ui-muted">{{ __('Edit the question bank') }}</summary>
                    <div class="mt-2 divide-y divide-ui-line">
                        @foreach ($this->templates as $template)
                            <div class="flex items-center gap-2 py-1.5" wire:key="qta-{{ $template->id }}">
                                <input type="checkbox" @checked($template->is_active) wire:click="toggleActive({{ $template->id }})" title="{{ __('Active in the bank') }}">
                                @if ($editingId === $template->id && $editingWhere === 'list')
                                    <div class="min-w-0 flex-1">@include('partials.question-edit-form')</div>
                                @else
                                    <span class="{{ $template->is_active ? '' : 'text-zinc-400 line-through' }} min-w-0 flex-1">{{ $template->text }}</span>
                                    <button type="button" wire:click="editTemplate({{ $template->id }})" class="shrink-0 rounded-lg bg-ui-raised px-2.5 py-1 text-xs font-semibold">{{ __('Edit') }}</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <x-ui.text class="mt-2 text-xs">{{ __('A changed question also changes in matchdays where nobody has answered it yet.') }}</x-ui.text>
                </details>
            @endif
        </x-ui.card>
    @endif
</div>
