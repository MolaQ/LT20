<?php

use App\Enums\MatchdayStatus;
use App\Models\Automat;
use App\Models\Matchday;
use App\Models\User;
use App\Support\Audit;
use App\Support\Automats;
use App\Support\Players;
use App\Support\TipRules;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Automaty (16.11.2026): admin wybiera istniejącą drużynę (wyszukiwanie po nazwie albo e-mailu z podpowiedziami na żywo)
 * i ustawia strategię typu oraz porę gry przed meczem.
 * Skład (kadra Lecha od największego doświadczenia) i pytania (najczęstsze odpowiedzi, 2 OF i 2 DEF) są wspólne
 * (App\Support\Automats). Dla innych graczy to zwykłe zespoły: znacznik widać tylko tutaj. Tylko Admin.
 */
new class extends Component {
    /** Automat w edycji: null = brak formularza, 0 = nowy. */
    public ?int $editId = null;

    /** Wybrana drużyna (konto gracza) i fraza wyszukiwania. */
    public ?int $userId = null;

    public string $search = '';

    public string $tipMode = Automat::MODE_POPULAR;

    public int $tipLech = 2;

    public int $tipOpponent = 1;

    public int $minutesBefore = 60;

    public bool $active = true;

    public function render(): View
    {
        return $this->view()->title(__('Automats'));
    }

    #[Computed]
    public function automats()
    {
        return Automat::with('user:id,name,team_name,email')->get()
            ->sortBy(fn (Automat $automat) => mb_strtolower((string) ($automat->user->team_name ?: $automat->user->name)))
            ->values();
    }

    /** Najbliższa kolejka do typowania: kiedy zagra każdy automat i jaki typ oddałby teraz. */
    #[Computed]
    public function nextMatchday(): ?Matchday
    {
        return Matchday::where('status', MatchdayStatus::Planned->value)->whereNotNull('kickoff_at')
            ->where('kickoff_at', '>', now())->orderBy('kickoff_at')->first();
    }

    /**
     * Podpowiedzi drużyn do wpisanej frazy (od 2 liter): nazwa drużyny, nazwa wyświetlana albo e-mail, bez automatów.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed]
    public function matches()
    {
        $term = Str::squish($this->search);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.$term.'%';

        return Players::eligible()
            ->whereNotIn('id', Automat::select('user_id'))
            ->where(fn ($q) => $q->where('team_name', 'like', $like)->orWhere('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderByRaw('COALESCE(team_name, name)')
            ->limit(8)
            ->get(['id', 'name', 'team_name', 'email']);
    }

    #[Computed]
    public function picked(): ?User
    {
        return $this->userId ? User::find($this->userId, ['id', 'name', 'team_name', 'email']) : null;
    }

    public function pick(int $id): void
    {
        $this->userId = $id;
        $this->search = '';
        $this->resetValidation('userId');
    }

    public function unpick(): void
    {
        $this->userId = null;
    }

    #[Computed]
    public function popularTip(): ?array
    {
        return $this->nextMatchday ? Automats::popularTip($this->nextMatchday) : null;
    }

    public function add(): void
    {
        $this->resetValidation();
        $this->reset('userId', 'search', 'tipMode', 'tipLech', 'tipOpponent', 'minutesBefore', 'active');
        $this->editId = 0;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $automat = Automat::with('user')->findOrFail($id);
        $this->editId = $automat->id;
        $this->userId = $automat->user_id;
        $this->search = '';
        $this->tipMode = $automat->tip_mode;
        $this->tipLech = $automat->tip_lech;
        $this->tipOpponent = $automat->tip_opponent;
        $this->minutesBefore = $automat->minutes_before;
        $this->active = $automat->active;
    }

    public function save(): void
    {
        $automat = $this->editId ? Automat::findOrFail($this->editId) : null;

        $this->validate([
            'userId' => ['required', 'integer', 'exists:users,id', 'unique:automats,user_id'.($automat ? ','.$automat->id : '')],
            'tipMode' => ['required', 'in:'.implode(',', Automat::MODES)],
            'tipLech' => ['required', 'integer', 'min:0', 'max:'.TipRules::MAX_GOALS],
            'tipOpponent' => ['required', 'integer', 'min:0', 'max:'.TipRules::MAX_GOALS],
            'minutesBefore' => ['required', 'integer', 'min:5', 'max:720'],
        ], [], [
            'userId' => __('Team'), 'tipLech' => 'Lech', 'tipOpponent' => __('Opponent'),
            'minutesBefore' => __('Minutes before kickoff'),
        ]);

        $automat ??= new Automat;
        $data = [
            'user_id' => (int) $this->userId,
            'tip_mode' => $this->tipMode,
            'tip_lech' => $this->tipLech,
            'tip_opponent' => $this->tipOpponent,
            'minutes_before' => $this->minutesBefore,
            'active' => $this->active,
        ];
        $automat->fill($data)->save();

        $user = $automat->user()->first();
        Audit::log('automats.saved', $user, [], $data, $user?->team_name ?: $user?->name);
        $this->cancel();
        unset($this->automats);
        $this->dispatch('ui-toast', text: __('Saved.'), variant: 'success');
    }

    public function toggle(int $id): void
    {
        $automat = Automat::findOrFail($id);
        $automat->update(['active' => ! $automat->active]);
        unset($this->automats);
    }

    public function cancel(): void
    {
        $this->reset('editId', 'userId', 'search', 'tipMode', 'tipLech', 'tipOpponent', 'minutesBefore', 'active');
        $this->resetValidation();
    }

    /** Zdejmuje automat: zespół zostaje jako zwykły gracz (bez typów w kolejnych kolejkach). */
    public function delete(int $id): void
    {
        $automat = Automat::with('user')->findOrFail($id);
        Audit::log('automats.deleted', $automat->user, [], [], $automat->user->team_name);
        $automat->delete();
        unset($this->automats);
        $this->dispatch('ui-toast', text: __('Deleted.'), variant: 'success');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Automats') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Teams that play by themselves before each match: a tip, a squad and answers to questions. Other players see them as ordinary teams.') }}</x-ui.subheading>
        </div>
        <x-ui.btn variant="primary" icon="plus" wire:click="add">{{ __('Add an automat') }}</x-ui.btn>
    </div>

    {{-- Wspólne zasady automatów --}}
    <div class="grid gap-3 md:grid-cols-3">
        @foreach ([
            ['pencil-square', __('Tip'), __('A fixed score or the most common tip of the players at that moment. A tie in the vote goes to the score better for Lech, with no tips it is 1:0.')],
            ['user-group', __('Squad'), __('The Lech squad without unavailable and resting players, from the most experienced. :count players in each competition the team plays.', ['count' => \App\Support\Designation::value('max_players')])],
            ['question-mark-circle', __('Questions'), __('The most common answers of the players: the two with the strongest agreement as attack, the next two as defence.')],
        ] as [$icon, $title, $text])
            <div class="rounded-2xl border border-ui-line bg-ui-surface p-4">
                <div class="flex items-center gap-2 font-semibold"><x-ui.icon :name="$icon" variant="mini" class="text-club" />{{ $title }}</div>
                <p class="mt-1 text-sm text-ui-muted">{{ $text }}</p>
            </div>
        @endforeach
    </div>

    @if ($editId !== null)
        <form wire:submit="save" class="space-y-5 rounded-2xl border border-club/40 bg-ui-surface p-5 shadow-sm" x-on:keydown.escape="$wire.cancel()">
            {{-- Drużyna: wyszukiwanie po nazwie albo e-mailu, podpowiedzi na żywo --}}
            <div>
                <p class="mb-1 text-sm font-semibold">{{ __('Team') }}</p>
                @if ($picked = $this->picked)
                    <div class="flex items-center gap-3 rounded-xl border border-club/40 bg-club/5 px-3 py-2.5">
                        <x-ui.icon name="cpu-chip" variant="mini" class="shrink-0 text-club" />
                        <span class="min-w-0 flex-1 truncate"><b>{{ $picked->team_name ?: $picked->name }}</b> <span class="text-ui-muted">({{ $picked->email }})</span></span>
                        @if (! $editId)
                            <button type="button" wire:click="unpick" class="text-sm font-semibold text-club hover:underline">{{ __('Change') }}</button>
                        @endif
                    </div>
                @else
                    <div class="relative" x-data="{ open: true }" x-on:click.outside="open = false">
                        <x-ui.input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('Type the team name or email')"
                            autocomplete="off" x-on:focus="open = true" x-on:input="open = true" />
                        @if (mb_strlen(trim($search)) >= 2)
                            <div x-show="open" class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-ui-line bg-ui-surface shadow-lg">
                                @forelse ($this->matches as $match)
                                    <button type="button" wire:key="match-{{ $match->id }}" wire:click="pick({{ $match->id }})"
                                        class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm transition hover:bg-ui-raised">
                                        <span class="truncate"><b>{{ $match->team_name ?: $match->name }}</b> <span class="text-ui-muted">({{ $match->email }})</span></span>
                                    </button>
                                @empty
                                    <p class="px-3 py-2.5 text-sm text-ui-muted">{{ __('No team found.') }}</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @endif
                <x-ui.error name="userId" />
            </div>

            <div class="grid gap-5 lg:grid-cols-[1fr_16rem]">
                <div>
                    <p class="mb-2 text-sm font-semibold">{{ __('Tip') }}</p>
                    <x-ui.segmented wire:model.live="tipMode" :options="['popular' => __('Most common tip'), 'fixed' => __('Fixed score')]" class="max-w-md" />
                    @if ($tipMode === 'fixed')
                        <div class="mt-3 flex items-center gap-2">
                            <span class="text-sm font-semibold">Lech</span>
                            <x-ui.input wire:model="tipLech" type="number" min="0" max="{{ \App\Support\TipRules::MAX_GOALS }}" class="w-20" />
                            <span class="font-display text-xl font-bold">:</span>
                            <x-ui.input wire:model="tipOpponent" type="number" min="0" max="{{ \App\Support\TipRules::MAX_GOALS }}" class="w-20" />
                            <span class="text-sm font-semibold">{{ __('Opponent') }}</span>
                        </div>
                    @else
                        <p class="mt-2 text-xs text-ui-muted">{{ __('Counted at the moment the automat plays, without other automats.') }}</p>
                    @endif
                </div>
                <div class="space-y-3">
                    <x-ui.input wire:model="minutesBefore" type="number" min="5" max="720" :label="__('Minutes before kickoff')" :suffix="__('min')" />
                    <x-ui.switch wire:model="active" :label="__('Active')" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.btn variant="ghost" type="button" wire:click="cancel">{{ __('Cancel') }}</x-ui.btn>
                <x-ui.btn variant="primary" type="submit">{{ __('Save') }}</x-ui.btn>
            </div>
        </form>
    @endif

    @if ($this->automats->isEmpty())
        <x-ui.callout icon="information-circle">{{ __('No automats yet.') }}</x-ui.callout>
    @else
        @php
            $next = $this->nextMatchday;
            $popular = $this->popularTip;
        @endphp
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->automats as $automat)
                @php
                    $tip = $automat->tip_mode === 'fixed' ? [$automat->tip_lech, $automat->tip_opponent] : $popular;
                    $playsAt = $next?->kickoff_at->copy()->subMinutes($automat->minutes_before);
                    $done = $next && $automat->last_matchday_id === $next->id;
                @endphp
                <div wire:key="automat-{{ $automat->id }}" @class(['overflow-hidden rounded-2xl border bg-ui-surface shadow-sm transition', 'border-ui-line' => $automat->active, 'border-dashed border-ui-line opacity-60' => ! $automat->active])>
                    <div class="flex items-start gap-3 p-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-club/10 text-club"><x-ui.icon name="cpu-chip" variant="mini" /></span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('team.show', $automat->user_id) }}" wire:navigate class="block truncate font-semibold hover:underline">{{ $automat->user->team_name ?: $automat->user->name }}</a>
                            <p class="truncate text-xs text-ui-muted">{{ $automat->user->email }}</p>
                        </div>
                        <x-ui.switch wire:click="toggle({{ $automat->id }})" :checked="$automat->active" :aria-label="__('Active')" />
                    </div>
                    <div class="grid grid-cols-2 gap-px border-t border-ui-line bg-ui-line text-sm">
                        <div class="bg-ui-surface px-4 py-2.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-ui-muted">{{ $automat->tip_mode === 'fixed' ? __('Fixed score') : __('Most common tip') }}</p>
                            <p class="font-display text-lg font-bold tabular-nums">{{ $tip ? $tip[0].':'.$tip[1] : '—' }}</p>
                        </div>
                        <div class="bg-ui-surface px-4 py-2.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-ui-muted">{{ __(':minutes min before kickoff', ['minutes' => $automat->minutes_before]) }}</p>
                            <p class="font-semibold tabular-nums">
                                @if ($done)
                                    <span class="text-green-600 dark:text-green-400">{{ __('Played at :time', ['time' => $automat->last_run_at?->format('H:i')]) }}</span>
                                @elseif ($playsAt)
                                    {{ $playsAt->format('d.m, H:i') }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-1 border-t border-ui-line px-2 py-1.5">
                        <x-ui.btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $automat->id }})">{{ __('Edit') }}</x-ui.btn>
                        <x-ui.btn variant="ghost" size="sm" icon="trash" wire:click="delete({{ $automat->id }})" wire:confirm="{{ __('Remove the automat? The team stays as an ordinary player.') }}" :aria-label="__('Delete')" />
                    </div>
                </div>
            @endforeach
        </div>
        @if ($next)
            <p class="text-xs text-ui-muted">{{ __('Next matchday: :number, kickoff :date. The most common tip may still change until the automat plays.', ['number' => $next->number, 'date' => $next->kickoff_at->format('d.m, H:i')]) }}</p>
        @endif
    @endif
</div>
