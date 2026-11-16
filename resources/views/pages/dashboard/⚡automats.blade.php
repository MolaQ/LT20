<?php

use App\Enums\MatchdayStatus;
use App\Models\Automat;
use App\Models\Matchday;
use App\Models\User;
use App\Support\Audit;
use App\Support\Automats;
use App\Support\Designation;
use App\Support\Players;
use App\Support\Questions;
use App\Support\Squad;
use App\Support\TipRules;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Automaty (16.11.2026): admin wybiera istniejącą drużynę (wyszukiwanie po nazwie albo e-mailu z podpowiedziami na żywo)
 * i ustawia strategię typu oraz porę gry przed meczem.
 * Skład: strategia osobno w każdych rozgrywkach i na mecz z botem, własna kolejność zawodników, a pod przyciskiem
 * „Skład” podgląd zawodników na najbliższą kolejkę z ręczną poprawką (App\Support\Automats::plan).
 * Pytania (najczęstsze odpowiedzi, 2 OF i 2 DEF) są wspólne. Dla innych graczy to zwykłe zespoły: znacznik widać
 * tylko tutaj. Tylko Admin.
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

    /** @var array<string, string> typ rozgrywek => strategia składu */
    public array $squadRules = [];

    public string $squadVsBot = Automat::SQUAD_SAME;

    /** @var array<int, int> własna kolejność zawodników */
    public array $squadPriority = [];

    /** Automat z otwartym podglądem składu i poprawiany skład: typ rozgrywek => id zawodników. */
    public ?int $squadId = null;

    /** @var array<string, array<int, int>> */
    public array $squadDraft = [];

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

    /** Kadra Lecha bez niedostępnych: id => zawodnik (nazwy w podglądzie i w liście własnej kolejności). */
    #[Computed]
    public function squadPlayers()
    {
        return Squad::available()->keyBy('id');
    }

    /**
     * Podgląd składu otwartego automatu na najbliższą kolejkę: plan, zawodnicy wolni do dodania i limit w rozgrywkach.
     *
     * @return array{automat: Automat, plan: array<string, array<string, mixed>>, free: array<int, int>, max: int}|null
     */
    #[Computed]
    public function squadPanel(): ?array
    {
        $automat = $this->squadId ? Automat::with('user')->find($this->squadId) : null;

        if (! $automat || ! $this->nextMatchday) {
            return null;
        }

        return [
            'automat' => $automat,
            'plan' => Automats::plan($automat, $this->nextMatchday),
            'free' => Automats::squadOrder($automat->user, $this->nextMatchday),
            'max' => Designation::value('max_players'),
        ];
    }

    public function addPriority(int $playerId): void
    {
        if ($this->squadPlayers->has($playerId) && ! in_array($playerId, $this->squadPriority, true)) {
            $this->squadPriority[] = $playerId;
        }
    }

    public function removePriority(int $index): void
    {
        unset($this->squadPriority[$index]);
        $this->squadPriority = array_values($this->squadPriority);
    }

    public function raisePriority(int $index): void
    {
        if ($index > 0 && isset($this->squadPriority[$index])) {
            [$this->squadPriority[$index - 1], $this->squadPriority[$index]] = [$this->squadPriority[$index], $this->squadPriority[$index - 1]];
        }
    }

    public function openSquad(int $id): void
    {
        $this->squadId = $this->squadId === $id ? null : $id;
        unset($this->squadPanel);
        $this->squadDraft = collect($this->squadPanel['plan'] ?? [])->map(fn (array $row) => $row['players'])->all();
    }

    public function addToSquad(string $set, int $playerId): void
    {
        $panel = $this->squadPanel;

        if (! $panel || ! isset($panel['plan'][$set]) || ! in_array($playerId, $panel['free'], true)
            || count($this->squadDraft[$set] ?? []) >= $panel['max'] || in_array($playerId, array_merge(...array_values($this->squadDraft ?: [[]])), true)) {
            return;
        }

        $this->squadDraft[$set][] = $playerId;
    }

    public function removeFromSquad(string $set, int $index): void
    {
        unset($this->squadDraft[$set][$index]);
        $this->squadDraft[$set] = array_values($this->squadDraft[$set] ?? []);
    }

    /** Skład poprawiony ręcznie zostaje na tę kolejkę; jeśli automat już zagrał, od razu poprawia zapisany skład. */
    public function saveSquad(): void
    {
        $panel = $this->squadPanel;

        if (! $panel) {
            return;
        }

        $sets = [];
        foreach ($panel['plan'] as $set => $row) {
            $sets[$set] = array_values(array_map('intval', $this->squadDraft[$set] ?? []));
        }

        $automat = $panel['automat'];
        $automat->update(['squad_override' => ['matchday_id' => $this->nextMatchday->id, 'sets' => $sets]]);
        $this->replayIfPlayed($automat);
        Audit::log('automats.saved', $automat->user, [], ['squad' => $sets], $automat->user->team_name ?: $automat->user->name);
        unset($this->squadPanel);
        $this->dispatch('ui-toast', text: __('Saved.'), variant: 'success');
    }

    /** Wraca do strategii: skład liczy się znów sam. */
    public function resetSquad(): void
    {
        $automat = $this->squadPanel['automat'] ?? null;

        if (! $automat) {
            return;
        }

        $automat->update(['squad_override' => null]);
        $this->replayIfPlayed($automat);
        unset($this->squadPanel);
        $this->squadDraft = collect($this->squadPanel['plan'] ?? [])->map(fn (array $row) => $row['players'])->all();
    }

    private function replayIfPlayed(Automat $automat): void
    {
        $matchday = $this->nextMatchday;

        if ($matchday && $automat->last_matchday_id === $matchday->id && $matchday->isOpenForTips()) {
            Automats::pickSquad($automat->fresh('user'), $matchday);
        }
    }

    #[Computed]
    public function popularTip(): ?array
    {
        return $this->nextMatchday ? Automats::popularTip($this->nextMatchday) : null;
    }

    public function add(): void
    {
        $this->resetValidation();
        $this->reset('userId', 'search', 'tipMode', 'tipLech', 'tipOpponent', 'minutesBefore', 'active', 'squadRules', 'squadVsBot', 'squadPriority');
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
        $this->squadRules = collect(Questions::sets())->mapWithKeys(fn (string $set) => [$set => $automat->strategyFor($set)])->all();
        $this->squadVsBot = $automat->squad_vs_bot;
        $this->squadPriority = array_values(array_map('intval', $automat->squad_priority ?? []));
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
            'squadRules' => ['array'],
            'squadRules.*' => ['in:'.implode(',', Automat::SQUAD_STRATEGIES)],
            'squadVsBot' => ['required', 'in:'.implode(',', [Automat::SQUAD_SAME, ...Automat::SQUAD_STRATEGIES])],
            'squadPriority' => ['array'],
            'squadPriority.*' => ['integer', 'exists:lech_players,id'],
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
            'squad_rules' => array_intersect_key($this->squadRules, array_flip(Questions::sets())) ?: null,
            'squad_vs_bot' => $this->squadVsBot,
            'squad_priority' => array_values(array_unique(array_map('intval', $this->squadPriority))) ?: null,
        ];
        $automat->fill($data)->save();
        unset($this->squadPanel);

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
        $this->reset('editId', 'userId', 'search', 'tipMode', 'tipLech', 'tipOpponent', 'minutesBefore', 'active', 'squadRules', 'squadVsBot', 'squadPriority');
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
            ['user-group', __('Squad'), __('A strategy for each competition and for a match against a bot, up to :count players. Under Squad you see the players for the next matchday and can change them.', ['count' => \App\Support\Designation::value('max_players')])],
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

            {{-- Strategia składu: w każdych rozgrywkach, na mecz z botem i własna kolejność --}}
            @php
                $strategies = \App\Models\Automat::strategyLabels();
            @endphp
            <div class="space-y-3 border-t border-ui-line pt-5">
                <p class="text-sm font-semibold">{{ __('Squad') }}</p>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach (\App\Support\Questions::sets() as $set)
                        <x-ui.select wire:model.live="squadRules.{{ $set }}" :label="\App\Support\Questions::setLabel($set)">
                            @foreach ($strategies as $value => $label)
                                <x-ui.select.option :value="$value">{{ $label }}</x-ui.select.option>
                            @endforeach
                        </x-ui.select>
                    @endforeach
                    <x-ui.select wire:model.live="squadVsBot" :label="__('Against a bot')">
                        <x-ui.select.option value="same">{{ __('As in the competition') }}</x-ui.select.option>
                        @foreach ($strategies as $value => $label)
                            <x-ui.select.option :value="$value">{{ $label }}</x-ui.select.option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="rounded-xl bg-ui-raised/50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ui-muted">{{ __('Own order') }}</p>
                    <p class="mb-2 text-xs text-ui-muted">{{ __('Used by the Own order strategy. Players from the top, the rest by experience. Resting and unavailable players are skipped.') }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($squadPriority as $index => $playerId)
                            @if ($player = $this->squadPlayers[$playerId] ?? null)
                                <span wire:key="prio-{{ $playerId }}" class="flex items-center gap-1 rounded-full border border-ui-line bg-ui-surface py-1 pe-1 ps-2.5 text-sm">
                                    <b class="tabular-nums text-club">{{ $index + 1 }}.</b> {{ $player->name }}
                                    @if ($index > 0)
                                        <button type="button" wire:click="raisePriority({{ $index }})" class="grid size-6 place-items-center rounded-full text-ui-muted hover:bg-ui-raised" aria-label="{{ __('Move up') }}"><x-ui.icon name="chevron-left" variant="micro" /></button>
                                    @endif
                                    <button type="button" wire:click="removePriority({{ $index }})" class="grid size-6 place-items-center rounded-full text-ui-muted hover:bg-ui-raised" aria-label="{{ __('Delete') }}"><x-ui.icon name="x-mark" variant="micro" /></button>
                                </span>
                            @endif
                        @endforeach
                        <select x-on:change="if ($event.target.value) { $wire.addPriority(Number($event.target.value)); $event.target.value = '' }"
                            class="h-9 rounded-full border border-ui-line bg-ui-surface px-3 text-sm text-ui-ink">
                            <option value="">{{ __('Add a player') }}</option>
                            @foreach ($this->squadPlayers as $player)
                                @unless (in_array($player->id, $squadPriority, true))
                                    <option value="{{ $player->id }}">{{ $player->name }} ({{ $player->position->label() }})</option>
                                @endunless
                            @endforeach
                        </select>
                    </div>
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
                        <x-ui.btn variant="ghost" size="sm" icon="user-group" wire:click="openSquad({{ $automat->id }})" @class(['text-club' => $squadId === $automat->id])>{{ __('Squad') }}</x-ui.btn>
                        <x-ui.btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $automat->id }})">{{ __('Edit') }}</x-ui.btn>
                        <x-ui.btn variant="ghost" size="sm" icon="trash" wire:click="delete({{ $automat->id }})" wire:confirm="{{ __('Remove the automat? The team stays as an ordinary player.') }}" :aria-label="__('Delete')" />
                    </div>
                </div>
            @endforeach
        </div>
        {{-- Podgląd składu otwartego automatu na najbliższą kolejkę, z ręczną poprawką --}}
        @if ($squadId !== null)
            @if ($panel = $this->squadPanel)
                @php
                    $squadAutomat = $panel['automat'];
                    $taken = array_merge(...array_values($squadDraft ?: [[]]));
                    $strategies = \App\Models\Automat::strategyLabels();
                @endphp
                <section class="overflow-hidden rounded-2xl border border-club/40 bg-ui-surface shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ui-line px-5 py-3">
                        <div>
                            <p class="font-semibold">{{ __('Squad for matchday :number', ['number' => $next->number]) }}: {{ $squadAutomat->user->team_name ?: $squadAutomat->user->name }}</p>
                            <p class="text-xs text-ui-muted">{{ __('Resting and unavailable players are left out. Changes count only for this matchday.') }}</p>
                        </div>
                        <button type="button" wire:click="openSquad({{ $squadAutomat->id }})" class="grid size-8 place-items-center rounded-full text-ui-muted hover:bg-ui-raised" aria-label="{{ __('Close') }}"><x-ui.icon name="x-mark" variant="mini" /></button>
                    </div>
                    @if ($panel['plan'] === [])
                        <p class="px-5 py-4 text-sm text-ui-muted">{{ __('This team plays no competition in this matchday.') }}</p>
                    @else
                        <div class="divide-y divide-ui-line">
                            @foreach ($panel['plan'] as $set => $row)
                                <div wire:key="plan-{{ $set }}" class="grid gap-3 px-5 py-4 lg:grid-cols-[16rem_1fr]">
                                    <div>
                                        <p class="font-semibold">{{ $row['label'] }}</p>
                                        <p class="mt-0.5 text-xs text-ui-muted">
                                            {{ $row['opponents'] !== [] ? __('Against :teams', ['teams' => implode(', ', $row['opponents'])]) : __('No duel in this round') }}
                                        </p>
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @if ($row['bot'])<x-ui.badge size="sm" color="zinc" icon="cpu-chip">{{ __('Bot') }}</x-ui.badge>@endif
                                            @if ($row['manual'])
                                                <x-ui.badge size="sm" color="amber" icon="pencil">{{ __('Set by hand') }}</x-ui.badge>
                                            @else
                                                <x-ui.badge size="sm" color="blue">{{ $strategies[$row['strategy']] ?? $row['strategy'] }}</x-ui.badge>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach ($squadDraft[$set] ?? [] as $index => $playerId)
                                            @if ($player = $this->squadPlayers[$playerId] ?? null)
                                                <span wire:key="pick-{{ $set }}-{{ $playerId }}" class="flex items-center gap-1 rounded-full bg-club/10 py-1 pe-1 ps-2.5 text-sm font-medium">
                                                    {{ $player->name }} <span class="text-xs text-ui-muted">{{ $player->position->label() }}</span>
                                                    <button type="button" wire:click="removeFromSquad('{{ $set }}', {{ $index }})" class="grid size-6 place-items-center rounded-full text-ui-muted hover:bg-ui-raised" aria-label="{{ __('Delete') }}"><x-ui.icon name="x-mark" variant="micro" /></button>
                                                </span>
                                            @endif
                                        @endforeach
                                        @if (count($squadDraft[$set] ?? []) < $panel['max'])
                                            <select x-on:change="if ($event.target.value) { $wire.addToSquad(@js($set), Number($event.target.value)); $event.target.value = '' }"
                                                class="h-9 rounded-full border border-dashed border-ui-line bg-ui-surface px-3 text-sm text-ui-ink">
                                                <option value="">{{ __('Add a player') }} ({{ count($squadDraft[$set] ?? []) }}/{{ $panel['max'] }})</option>
                                                @foreach ($panel['free'] as $playerId)
                                                    @if (! in_array($playerId, $taken, true) && ($player = $this->squadPlayers[$playerId] ?? null))
                                                        <option value="{{ $playerId }}">{{ $player->name }} ({{ $player->position->label() }})</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap justify-end gap-2 border-t border-ui-line px-5 py-3">
                            @if (($squadAutomat->squad_override['matchday_id'] ?? null) === $next->id)
                                <x-ui.btn variant="ghost" icon="arrow-path" wire:click="resetSquad">{{ __('Back to the strategy') }}</x-ui.btn>
                            @endif
                            <x-ui.btn variant="primary" wire:click="saveSquad">{{ __('Save the squad for this matchday') }}</x-ui.btn>
                        </div>
                    @endif
                </section>
            @else
                <x-ui.callout icon="information-circle">{{ __('No upcoming matchday yet.') }}</x-ui.callout>
            @endif
        @endif

        @if ($next)
            <p class="text-xs text-ui-muted">{{ __('Next matchday: :number, kickoff :date. The most common tip may still change until the automat plays.', ['number' => $next->number, 'date' => $next->kickoff_at->format('d.m, H:i')]) }}</p>
        @endif
    @endif
</div>
