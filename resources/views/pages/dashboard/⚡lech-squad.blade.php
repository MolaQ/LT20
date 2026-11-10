<?php

use App\Enums\Permission;
use App\Enums\PlayerPosition;
use App\Enums\PlayerStatus;
use App\Models\LechPlayer;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kadra Lecha: zawodnicy z pozycją, numerem, wychowankiem i statusem (zmiana statusu od razu z listy).
 * Z tej kadry gracze desygnują zawodników do gry. Obok sumy dokonań z raportów meczów (mecze, skład, czas gry, gole,
 * asysty, samobóje, czyste konta, kartki, doświadczenie). Podgląd: season-list, zmiany: season-edit.
 */
new class extends Component {
    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'position', except: '')]
    public string $positionFilter = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    /** id zawodnika => status (lista wyboru w tabeli zapisuje się od razu) */
    public array $statuses = [];

    // Formularz zawodnika
    public ?int $playerId = null;
    public string $name = '';
    public string $number = '';
    public string $position = 'midfielder';
    public bool $isAcademy = false;
    public string $status = 'regular';

    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->loadStatuses();
    }

    public function render(): View
    {
        return $this->view()->title(__('Lech squad'));
    }

    private function loadStatuses(): void
    {
        $this->statuses = LechPlayer::pluck('status', 'id')->map(fn($s) => $s->value)->all();
    }

    /** Zawodnicy według pozycji (bramkarze pierwsi), numeru i nazwiska. */
    #[Computed]
    public function players()
    {
        $order = PlayerPosition::values();

        return LechPlayer::query()
            ->when(trim($this->search) !== '', fn($q) => $q->where('name', 'like', '%' . trim($this->search) . '%'))
            ->when(PlayerPosition::tryFrom($this->positionFilter), fn($q, $p) => $q->where('position', $p->value))
            ->when(PlayerStatus::tryFrom($this->statusFilter), fn($q, $s) => $q->where('status', $s->value))
            ->get()
            ->sortBy(fn($p) => [array_search($p->position->value, $order, true), $p->number ?? 999, $p->name])
            ->values();
    }

    /** Dorobek zawodników ze wszystkich raportów meczów (także archiwalnych), Support\MatchReport::playerTotals. */
    #[Computed]
    public function totals(): array
    {
        return \App\Support\MatchReport::playerTotals();
    }

    /** Liczba zawodników w każdym statusie (kafelki nad tabelą). */
    #[Computed]
    public function statusCounts(): array
    {
        return LechPlayer::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    }

    /* ==================================================================
     | STATUS Z LISTY
     * ================================================================*/

    public function updatedStatuses(mixed $value, string $key): void
    {
        $this->authorizeEdit();

        $player = LechPlayer::findOrFail((int) $key);
        $status = PlayerStatus::from((string) $value);
        $old = $player->status->label();
        $player->update(['status' => $status]);

        Audit::log('lech_player.status', null, ['status' => $old], ['status' => $status->label()], $player->name);

        unset($this->players, $this->statusCounts);
        $this->dispatch('ui-toast', variant: 'success', text: __('Status of :name: :status.', ['name' => $player->name, 'status' => $status->label()]));
    }

    /* ==================================================================
     | FORMULARZ ZAWODNIKA
     * ================================================================*/

    public function create(): void
    {
        $this->authorizeEdit();
        $this->resetForm();
        $this->dispatch('ui-sheet', name: 'player-form');
    }

    public function edit(int $id): void
    {
        $this->authorizeEdit();
        $player = LechPlayer::findOrFail($id);

        $this->resetValidation();
        $this->playerId = $player->id;
        $this->name = $player->name;
        $this->number = (string) ($player->number ?? '');
        $this->position = $player->position->value;
        $this->isAcademy = $player->is_academy;
        $this->status = $player->status->value;

        $this->dispatch('ui-sheet', name: 'player-form');
    }

    public function save(): void
    {
        $this->authorizeEdit();

        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:80', Rule::unique('lech_players', 'name')->ignore($this->playerId)],
            'number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'position' => ['required', Rule::enum(PlayerPosition::class)],
            'status' => ['required', Rule::enum(PlayerStatus::class)],
        ], [], [
            'name' => __('Name'),
            'number' => __('Number'),
            'position' => __('Position'),
            'status' => __('Status'),
        ]);

        $player = $this->playerId ? LechPlayer::findOrFail($this->playerId) : new LechPlayer();
        $before = $player->exists ? $player->only(['name', 'number']) : [];

        $player->fill([
            'name' => $this->name,
            'number' => $this->number !== '' ? (int) $this->number : null,
            'position' => $this->position,
            'is_academy' => $this->isAcademy,
            'status' => $this->status,
        ])->save();

        Audit::log($before === [] ? 'lech_player.created' : 'lech_player.updated', null, $before, $player->only(['name', 'number']), $player->name);

        $this->dispatch('ui-sheet', name: 'player-form', open: false);
        $this->resetForm();
        $this->loadStatuses();
        unset($this->players, $this->statusCounts);
        $this->dispatch('ui-toast', variant: 'success', text: __('Player saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('playerId', 'name', 'number', 'position', 'isAcademy', 'status');
        $this->resetValidation();
    }

    /* ==================================================================
     | USUWANIE
     * ================================================================*/

    public function confirmDelete(int $id): void
    {
        $this->authorizeEdit();
        $this->deleteId = LechPlayer::findOrFail($id)->id;
        $this->dispatch('ui-sheet', name: 'delete-player');
    }

    public function delete(): void
    {
        $this->authorizeEdit();
        $player = LechPlayer::findOrFail((int) $this->deleteId);

        $player->delete();
        Audit::log('lech_player.deleted', null, ['name' => $player->name], [], $player->name);

        $this->dispatch('ui-sheet', name: 'delete-player', open: false);
        $this->reset('deleteId');
        $this->loadStatuses();
        unset($this->players, $this->statusCounts);
        $this->dispatch('ui-toast', variant: 'success', text: __('Player deleted.'));
    }

    private function authorizeEdit(): void
    {
        abort_unless(auth()->user()?->can(Permission::SeasonEdit->value), 403);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Lech squad') }}</x-ui.heading>
            <x-ui.subheading>
                {{ __('Lech players that players will designate for the matchday.') }}
            </x-ui.subheading>
        </div>

        @can(\App\Enums\Permission::SeasonEdit->value)
            <div class="flex gap-2">
                <x-ui.btn variant="primary" icon="plus" wire:click="create">{{ __('New player') }}</x-ui.btn>
            </div>
        @endcan
    </div>

    {{-- Liczba zawodników w statusach: kafelek działa jak filtr --}}
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Enums\PlayerStatus::cases() as $case)
            @php
                $selected = $statusFilter === $case->value;
            @endphp
            <button type="button" wire:click="$set('statusFilter', '{{ $selected ? '' : $case->value }}')"
                class="{{ $selected ? 'border-lech-600 bg-lech-50 text-club dark:bg-lech-950/50 ' : 'border-ui-line hover:border-zinc-300 ' }} flex items-center gap-2 rounded-full border px-3 py-1 text-sm">
                <span>{{ $case->label() }}</span>
                <span class="tabular-nums text-ui-muted">{{ $this->statusCounts[$case->value] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search player...')" />
        </div>
        <x-ui.filter-pills model="positionFilter" :value="$positionFilter" :label="__('Position')" class="w-full"
            :options="['' => __('All positions')] + collect(\App\Enums\PlayerPosition::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" />
    </div>

    @php
        // Sumy z raportów meczów: skrót kolumny i opis w podpowiedzi.
        $statCols = [
            'matches' => [__('M'), __('Matches')],
            'starts' => [__('XI'), __('Starting eleven')],
            'subs' => [__('Sub'), __('On the bench')],
            'play_time' => [__('Time'), __('Playing time in halves')],
            'goals' => ['G', __('Goals')],
            'assists' => ['A', __('Assists')],
            'own_goals' => [__('OG'), __('Own goals')],
            'clean_sheets' => [__('CS'), __('Clean sheets')],
            'experience' => [__('Exp.'), __('Experience')],
        ];
    @endphp

    <x-ui.table>
        <x-ui.table.columns>
            <x-ui.table.column class="w-12">#</x-ui.table.column>
            <x-ui.table.column>{{ __('Player') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Position') }}</x-ui.table.column>
            @foreach ($statCols as [$short, $long])
                <x-ui.table.column align="center"><span title="{{ $long }}">{{ $short }}</span></x-ui.table.column>
            @endforeach
            <x-ui.table.column align="center">{{ __('Cards') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Status') }}</x-ui.table.column>
            <x-ui.table.column />
        </x-ui.table.columns>

        <x-ui.table.rows>
            @forelse ($this->players as $player)
                <x-ui.table.row :key="$player->id">
                    <x-ui.table.cell class="tabular-nums text-ui-muted">{{ $player->number ?? '–' }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $player->name }}</span>
                            @if ($player->is_academy)
                                <x-ui.badge size="sm" color="blue" icon="academic-cap">{{ __('Academy') }}</x-ui.badge>
                            @endif
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell>{{ $player->position->label() }}</x-ui.table.cell>
                    @php
                        $total = $this->totals[$player->id] ?? null;
                    @endphp
                    @foreach (array_keys($statCols) as $col)
                        <x-ui.table.cell align="center" class="tabular-nums {{ $col === 'experience' ? 'font-semibold' : '' }} {{ ($total[$col] ?? 0) ? '' : 'text-zinc-300 dark:text-zinc-600' }}">
                            {{ $col === 'experience' ? \App\Support\ClubStats::experience($total[$col] ?? 0) : \App\Support\ClubStats::format($total[$col] ?? 0) }}
                        </x-ui.table.cell>
                    @endforeach
                    <x-ui.table.cell align="center" class="text-xs tabular-nums">
                        @include('partials.card-counts', ['yellow' => $total['yellow'] ?? 0, 'red' => $total['red'] ?? 0])
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <x-ui.select size="sm" wire:model.live="statuses.{{ $player->id }}" class="min-w-44"
                            :disabled="! auth()->user()->can(\App\Enums\Permission::SeasonEdit->value)">
                            @foreach (\App\Enums\PlayerStatus::cases() as $case)
                                <x-ui.select.option :value="$case->value">{{ $case->label() }}</x-ui.select.option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.table.cell>
                    <x-ui.table.cell class="text-right">
                        @can(\App\Enums\Permission::SeasonEdit->value)
                            <div class="flex justify-end gap-1">
                                <x-ui.btn size="xs" variant="ghost" icon="pencil" wire:click="edit({{ $player->id }})" :aria-label="__('Edit')" />
                                <x-ui.btn size="xs" variant="ghost" icon="trash" wire:click="confirmDelete({{ $player->id }})" :aria-label="__('Delete')" />
                            </div>
                        @endcan
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.row>
                    <x-ui.table.cell colspan="15">
                        <x-ui.text>{{ __('No players. Add them here or run the squad seeder.') }}</x-ui.text>
                        <code class="mt-1 block text-xs">php artisan db:seed --class=LechSquadSeeder</code>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforelse
        </x-ui.table.rows>
    </x-ui.table>

    <x-ui.modal name="player-form" class="w-full md:w-[32rem]" @close="$wire.resetForm()">
        <form wire:submit="save" class="space-y-5">
            <x-ui.heading size="lg">{{ $playerId ? __('Edit player') : __('New player') }}</x-ui.heading>

            <div class="grid gap-4 sm:grid-cols-[1fr_6rem]">
                <x-ui.input wire:model="name" :label="__('Name')" maxlength="80" />
                <x-ui.input wire:model="number" type="number" min="1" max="99" :label="__('Number')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model="position" :label="__('Position')">
                    @foreach (\App\Enums\PlayerPosition::cases() as $case)
                        <x-ui.select.option :value="$case->value">{{ $case->label() }}</x-ui.select.option>
                    @endforeach
                </x-ui.select>

                <x-ui.select wire:model="status" :label="__('Status')">
                    @foreach (\App\Enums\PlayerStatus::cases() as $case)
                        <x-ui.select.option :value="$case->value">{{ $case->label() }}</x-ui.select.option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.switch wire:model="isAcademy" :label="__('Lech Academy graduate')" />

            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost" type="button">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" type="submit">{{ __('Save') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="delete-player" class="w-full md:w-[28rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('Delete player') }}</x-ui.heading>
            <x-ui.text>{{ __('Delete this player from the squad?') }}</x-ui.text>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="delete">{{ __('Delete') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
</div>
