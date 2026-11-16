<?php

use App\Enums\MatchdayStatus;
use App\Models\Automat;
use App\Models\Matchday;
use App\Models\User;
use App\Support\Audit;
use App\Support\Automats;
use App\Support\Players;
use App\Support\TeamRules;
use App\Support\TipRules;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Automaty (16.11.2026): admin zakłada zespół (nazwa i e-mail) i wybiera strategię typu oraz porę gry przed meczem.
 * Skład (kadra Lecha od największego doświadczenia) i pytania (najczęstsze odpowiedzi, 2 OF i 2 DEF) są wspólne
 * (App\Support\Automats). Dla innych graczy to zwykłe zespoły: znacznik widać tylko tutaj. Tylko Admin.
 */
new class extends Component {
    /** Automat w edycji: null = brak formularza, 0 = nowy. */
    public ?int $editId = null;

    public string $teamName = '';

    public string $email = '';

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

    #[Computed]
    public function popularTip(): ?array
    {
        return $this->nextMatchday ? Automats::popularTip($this->nextMatchday) : null;
    }

    public function add(): void
    {
        $this->resetValidation();
        $this->reset('teamName', 'email', 'tipMode', 'tipLech', 'tipOpponent', 'minutesBefore', 'active');
        $this->editId = 0;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $automat = Automat::with('user')->findOrFail($id);
        $this->editId = $automat->id;
        $this->teamName = (string) ($automat->user->team_name ?: $automat->user->name);
        $this->email = $automat->user->email;
        $this->tipMode = $automat->tip_mode;
        $this->tipLech = $automat->tip_lech;
        $this->tipOpponent = $automat->tip_opponent;
        $this->minutesBefore = $automat->minutes_before;
        $this->active = $automat->active;
    }

    public function save(): void
    {
        $this->teamName = Str::squish($this->teamName);
        $this->email = mb_strtolower(trim($this->email));
        $automat = $this->editId ? Automat::with('user')->findOrFail($this->editId) : null;

        $this->validate([
            'teamName' => TeamRules::name($automat?->user_id),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'.($automat ? ','.$automat->user_id : '')],
            'tipMode' => ['required', 'in:'.implode(',', Automat::MODES)],
            'tipLech' => ['required', 'integer', 'min:0', 'max:'.TipRules::MAX_GOALS],
            'tipOpponent' => ['required', 'integer', 'min:0', 'max:'.TipRules::MAX_GOALS],
            'minutesBefore' => ['required', 'integer', 'min:5', 'max:720'],
        ], [], [
            'teamName' => __('Team name'), 'email' => __('Email address'), 'tipLech' => 'Lech', 'tipOpponent' => __('Opponent'),
            'minutesBefore' => __('Minutes before kickoff'),
        ]);

        if ($automat) {
            $automat->user->update(['team_name' => $this->teamName, 'email' => $this->email]);
        } else {
            // Zwykłe konto gracza: potwierdzony adres, rola User i miejsce na listach sezonów jak po rejestracji.
            $user = User::create(['name' => $this->teamName, 'team_name' => $this->teamName, 'email' => $this->email, 'password' => Str::password(32)]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Players::approveVerified($user);
            $automat = new Automat(['user_id' => $user->id]);
        }

        $data = [
            'tip_mode' => $this->tipMode,
            'tip_lech' => $this->tipLech,
            'tip_opponent' => $this->tipOpponent,
            'minutes_before' => $this->minutesBefore,
            'active' => $this->active,
        ];
        $automat->fill($data)->save();

        Audit::log('automats.saved', $automat->user()->first(), [], $data, $this->teamName);
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
        $this->reset('editId', 'teamName', 'email', 'tipMode', 'tipLech', 'tipOpponent', 'minutesBefore', 'active');
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
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="teamName" :label="__('Team name')" maxlength="40" />
                <x-ui.input wire:model="email" type="email" :label="__('Email address')" maxlength="255" />
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
