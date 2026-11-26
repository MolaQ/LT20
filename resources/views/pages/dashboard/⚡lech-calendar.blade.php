<?php

use App\Enums\MatchCompetition;
use App\Enums\MatchdayStatus;
use App\Enums\Permission;
use App\Models\LechMatch;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kalendarz meczów Lecha: wstępny terminarz, z którego kolejki sezonu wybierają mecz (panel „Mecze”, edycja kolejki).
 * Zmiana meczu przypisanego do nierozegranej kolejki od razu zmienia kolejkę (LechMatch::syncMatchday):
 * przełożony mecz robi z kolejki przełożoną, a typy graczy zostają do wyboru nowego meczu.
 * Podgląd: season-list, zmiany: season-edit.
 */
new class extends Component {
    // Pokaż także mecze rozegrane i z przeszłości.
    #[Url(as: 'all', except: false)]
    public bool $showAll = false;

    // --- formularz meczu ---
    public ?int $editId = null;
    public string $opponent = '';
    public bool $isHome = true;
    public string $competition = 'league';
    public string $kickoffAt = '';
    public bool $postponed = false;
    public string $note = '';

    public ?int $deleteId = null;

    public function render(): View
    {
        return $this->view()->title(__('Lech calendar'));
    }

    /** Mecze od najbliższego, bez terminu na końcu. Domyślnie bez meczów z przeszłości. */
    #[Computed]
    public function matches()
    {
        return LechMatch::with('matchday.season')
            ->when(!$this->showAll, fn($q) => $q->where(fn($q) => $q->whereNull('kickoff_at')->orWhere('kickoff_at', '>=', now()->subDay())))
            ->orderByRaw('kickoff_at is null')
            ->orderBy('kickoff_at')
            ->orderBy('id')
            ->get();
    }

    public function openForm(?int $id = null): void
    {
        $this->authorizeEdit();
        $this->reset('editId', 'opponent', 'isHome', 'competition', 'kickoffAt', 'postponed', 'note');
        $this->resetValidation();

        if ($id) {
            $match = LechMatch::findOrFail($id);

            if ($this->isPlayed($match)) {
                return;
            }

            $this->editId = $match->id;
            $this->opponent = $match->opponent;
            $this->isHome = $match->is_home;
            $this->competition = MatchCompetition::tryFrom($match->competition)?->value ?? MatchCompetition::DEFAULT->value;
            $this->kickoffAt = $match->kickoff_at?->format('Y-m-d\TH:i') ?? '';
            $this->postponed = $match->postponed;
            $this->note = (string) $match->note;
        }

        $this->dispatch('ui-sheet', name: 'lech-match-form');
    }

    public function save(): void
    {
        $this->authorizeEdit();
        $this->opponent = Str::squish($this->opponent);

        $this->validate(
            [
                'opponent' => ['required', 'string', 'max:80'],
                'isHome' => ['boolean'],
                'competition' => ['required', Rule::enum(MatchCompetition::class)],
                'kickoffAt' => ['nullable', 'date_format:Y-m-d\TH:i'],
                'postponed' => ['boolean'],
                'note' => ['nullable', 'string', 'max:120'],
            ],
            [],
            ['opponent' => __('Opponent'), 'competition' => __('Competition'), 'kickoffAt' => __('Kickoff'), 'note' => __('Note')],
        );

        $match = $this->editId ? LechMatch::findOrFail($this->editId) : new LechMatch();

        if ($match->exists && $this->isPlayed($match)) {
            return;
        }

        $before = $match->exists ? $this->snapshot($match) : [];
        $matchday = $match->matchday()->first();
        $fixtureBefore = $matchday?->isFilled() ? [$matchday->fixture, $matchday->kickoff_at?->format('Y-m-d H:i'), $matchday->status] : null;

        $match
            ->fill([
                'opponent' => $this->opponent,
                'is_home' => $this->isHome,
                'competition' => $this->competition,
                'kickoff_at' => $this->kickoffAt !== '' ? Carbon::createFromFormat('Y-m-d\TH:i', $this->kickoffAt) : null,
                'postponed' => $this->postponed,
                'note' => trim($this->note) ?: null,
            ])
            ->save();

        $warning = $match->syncMatchday();
        Audit::log($before ? 'lech_match.updated' : 'lech_match.created', null, $before, $this->snapshot($match), $match->fixture());

        // Informacje systemowe: zmiana meczu w kolejce (przełożenie, nowy termin).
        if ($matchday && ($matchday = $matchday->fresh()) && $matchday->isFilled()) {
            $after = [$matchday->fixture, $matchday->kickoff_at?->format('Y-m-d H:i'), $matchday->status];

            if ($after !== $fixtureBefore) {
                \App\Support\SystemFeed::record(
                    'matches',
                    $matchday->status === MatchdayStatus::Postponed ? 'Match postponed: :fixture (matchday :number)' : 'Match updated: :fixture (matchday :number, :date)',
                    ['fixture' => $matchday->fixture, 'number' => $matchday->number, 'date' => $matchday->kickoff_at->format('d.m.Y H:i')],
                    'results',
                    ['round' => $matchday->number],
                );
            }
        }

        $this->dispatch('ui-sheet', name: 'lech-match-form', open: false);
        unset($this->matches);

        $warning !== null
            ? $this->dispatch('ui-toast', text: __('Match saved, but its matchday is postponed: :reason', ['reason' => $warning]), variant: 'warning')
            : $this->dispatch('ui-toast', text: __('Match saved.'), variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeEdit();
        $match = LechMatch::findOrFail($id);

        if ($match->matchday()->exists()) {
            $this->dispatch('ui-toast', text: __('This match is assigned to a matchday. Choose another match for the matchday first.'), variant: 'warning');

            return;
        }

        $this->deleteId = $match->id;
        $this->dispatch('ui-sheet', name: 'delete-lech-match');
    }

    public function delete(): void
    {
        $this->authorizeEdit();
        $match = LechMatch::findOrFail((int) $this->deleteId);

        if (!$match->matchday()->exists()) {
            Audit::log('lech_match.deleted', null, $this->snapshot($match), [], $match->fixture());
            $match->delete();
        }

        $this->deleteId = null;
        $this->dispatch('ui-sheet', name: 'delete-lech-match', open: false);
        unset($this->matches);
        $this->dispatch('ui-toast', text: __('Match deleted.'), variant: 'success');
    }

    /** Mecz rozegranej kolejki jest tylko do odczytu (wynik i punkty już policzone). */
    private function isPlayed(LechMatch $match): bool
    {
        if ($match->matchday()->where('status', MatchdayStatus::Played->value)->exists()) {
            $this->dispatch('ui-toast', text: __('A played matchday cannot be edited.'), variant: 'warning');

            return true;
        }

        return false;
    }

    private function snapshot(LechMatch $match): array
    {
        return [
            'opponent' => $match->opponent,
            'home_away' => $match->is_home ? __('Home') : __('Away'),
            'competition' => $match->competitionLabel(),
            'kickoff_at' => $match->kickoff_at?->format('Y-m-d H:i'),
            'postponed' => $match->postponed ? __('Yes') : __('No'),
        ];
    }

    private function authorizeEdit(): void
    {
        abort_unless(auth()->user()?->can(Permission::SeasonEdit->value), 403);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.matchday-tabs', ['tab' => 'calendar'])

    @php
        $upcoming = $this->matches->filter(fn ($m) => $m->kickoff_at && $m->kickoff_at->isFuture() && !$m->isPlaceholder());
        $nextMatch = $upcoming->first();
        $placeholders = $this->matches->filter(fn ($m) => $m->isPlaceholder())->count();
        $postponed = $this->matches->where('postponed', true)->count();
    @endphp
    <x-admin-hero :title="__('Lech calendar')" icon="calendar-days"
        :subtitle="__('Preliminary calendar of Lech matches. A new season adds an unnamed match for each matchday: fill in the opponent and the kickoff here or in Matchdays.')">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-admin-hero-stat :label="__('Next match')" :value="$nextMatch ? $nextMatch->opponent : '—'" class="[&_b]:truncate [&_b]:text-xl sm:[&_b]:text-2xl" />
            <x-admin-hero-stat :label="__('Next kickoff')" :value="$nextMatch?->kickoff_at?->translatedFormat('j M, H:i') ?? '—'" />
            <x-admin-hero-stat :label="__('Upcoming matches')" :value="$upcoming->count()" />
            <x-admin-hero-stat :label="__('To fill in')" :value="$placeholders + $postponed" :tone="$placeholders + $postponed > 0 ? 'warn' : 'good'" />
        </div>
    </x-admin-hero>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ui-line bg-ui-surface px-4 py-3">
        <x-ui.switch wire:model.live="showAll" :label="__('Show past matches')" />
        @can(\App\Enums\Permission::SeasonEdit->value)
            <x-ui.btn variant="primary" icon="plus" wire:click="openForm">{{ __('New match') }}</x-ui.btn>
        @endcan
    </div>

    @if ($this->matches->isEmpty())
        <x-ui.card>
            <x-ui.text>{{ __('No matches in the calendar yet.') }}</x-ui.text>
        </x-ui.card>
    @else
        {{-- Mecze jako karty: data w kafelku, barwy rywala, mecz, wynik, rozgrywki i kolejka gry --}}
        <div class="flex flex-col gap-2">
            @foreach ($this->matches as $match)
                @php
                    $matchday = $match->matchday;
                    $played = $matchday?->status === \App\Enums\MatchdayStatus::Played;
                    $past = $match->kickoff_at && $match->kickoff_at->isPast();
                    $stripe = !$match->isPlaceholder() && filled($match->opponent) ? \App\Models\Rival::stripe(\App\Models\Rival::colorsFor($match->opponent)) : '';
                    $isNext = $nextMatch && $nextMatch->id === $match->id;
                @endphp
                <article wire:key="lech-match-{{ $match->id }}" @class([
                    'relative flex flex-wrap items-center gap-4 overflow-hidden rounded-2xl border bg-ui-surface py-3 pe-3 ps-5 transition hover:shadow-md sm:flex-nowrap',
                    'border-club ring-2 ring-club/30' => $isNext,
                    'border-ui-line' => !$isNext,
                    'opacity-70' => $past && !$isNext,
                ])>
                    @if ($stripe)
                        <span class="absolute inset-y-0 start-0 w-1.5" style="{{ $stripe }}"></span>
                    @endif

                    {{-- Data: dzień i miesiąc w granatowym kafelku, godzina pod spodem --}}
                    <div class="lech-bar flex w-16 shrink-0 flex-col items-center rounded-xl py-1.5 text-center">
                        @if ($match->kickoff_at)
                            <span class="font-display text-xl font-black leading-none">{{ $match->kickoff_at->format('j') }}</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-white/70">{{ $match->kickoff_at->translatedFormat('M') }}</span>
                            <span class="mt-0.5 text-[11px] tabular-nums">{{ $match->kickoff_at->format('H:i') }}</span>
                        @else
                            <span class="py-2 text-xs font-bold">{{ __('No date') }}</span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($match->isPlaceholder())
                                {{-- „Mecz bez nazwy” z utworzenia sezonu: czeka na rywala i termin --}}
                                <x-ui.badge size="sm" color="amber" icon="pencil-square">{{ $match->fixture() }}</x-ui.badge>
                            @else
                                <span class="truncate font-display text-base font-bold">{{ $match->fixture() }}</span>
                                @if ($match->lech_goals !== null)
                                    <span class="rounded-lg bg-ui-raised px-2 py-0.5 font-display text-sm font-bold tabular-nums">{{ $match->lech_goals }}:{{ $match->opponent_goals }}</span>
                                @endif
                            @endif
                            @if ($match->postponed)
                                <x-ui.badge size="sm" color="amber">{{ __('Postponed') }}</x-ui.badge>
                            @endif
                            @if ($isNext)
                                <x-ui.badge size="sm" color="blue">{{ __('Next match') }}</x-ui.badge>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ui-muted">
                            <span>{{ $match->competitionLabel() }}</span>
                            @if (!$match->isPlaceholder())
                                <span>{{ $match->is_home ? __('Home') : __('Away') }}</span>
                            @endif
                            @if (filled($match->note))
                                <span class="italic">{{ $match->note }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($matchday)
                            <x-ui.badge size="sm" :color="$matchday->status->color()">
                                {{ $matchday->season?->title }}, {{ __('matchday :n', ['n' => $matchday->number]) }}
                            </x-ui.badge>
                        @else
                            <span class="text-xs text-ui-muted">{{ __('Not in any matchday') }}</span>
                        @endif
                        {{-- Wynik i raport meczu: kolejka w panelu wyników, mecz spoza gry (archiwalny) na osobnej stronie --}}
                        @if (!$match->isPlaceholder())
                            <x-ui.btn variant="ghost" size="sm" icon="clipboard-document-list"
                                :href="$matchday ? route('dashboard.results', ['season' => $matchday->season_id, 'matchday' => $matchday->number]) : route('dashboard.lech-match-report', $match)"
                                wire:navigate :aria-label="__('Match report')" :tooltip="__('Match report')" />
                        @endif
                        @can(\App\Enums\Permission::SeasonEdit->value)
                            @if (!$played)
                                <x-ui.btn variant="ghost" size="sm" icon="pencil-square" wire:click="openForm({{ $match->id }})" :aria-label="__('Edit')" />
                                @if (!$matchday)
                                    <x-ui.btn variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $match->id }})" :aria-label="__('Delete')" />
                                @endif
                            @endif
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- Modal: mecz w kalendarzu --}}
    <x-ui.modal name="lech-match-form" class="w-full md:w-[30rem]">
        <form wire:submit="save" class="space-y-6">
            <x-ui.heading size="lg">{{ $editId ? __('Edit match') : __('New match') }}</x-ui.heading>

            <x-ui.input wire:model="opponent" :label="__('Opponent')" placeholder="Legia Warszawa" list="rival-names" />
            @include('partials.rival-names')
            <x-ui.switch wire:model="isHome" :label="__('Home match')" />
            <x-ui.select wire:model="competition" :label="__('Competition')">
                @foreach (\App\Enums\MatchCompetition::options() as $value => $label)
                    <x-ui.select.option :value="$value">{{ $label }}</x-ui.select.option>
                @endforeach
            </x-ui.select>
            <x-ui.input wire:model="kickoffAt" type="datetime-local" :label="__('Kickoff')" :description="__('Polish time. Can be empty when the date is not known yet.')" />
            <x-ui.switch wire:model="postponed" :label="__('Postponed')"
                :description="__('The matchday with this match waits for a new match. The tips of the players stay.')" />
            <x-ui.input wire:model="note" :label="__('Note')" />

            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost" type="button">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" type="submit">{{ __('Save') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="delete-lech-match" class="w-full md:w-[28rem]">
        <div class="space-y-6">
            <x-ui.heading size="lg">{{ __('Delete this match?') }}</x-ui.heading>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost" type="button">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="delete">{{ __('Delete') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
</div>
