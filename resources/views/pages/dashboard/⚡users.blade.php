<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Roster;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    public const ADMIN_ROLE = RoleName::Admin->value;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    #[Url(except: 'name')]
    public string $sortBy = 'name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    // Edytowany użytkownik
    public ?int $userId = null;
    public string $userName = '';
    /** @var array<int, string> nazwy zaznaczonych ról */
    public array $selected = [];

    /** @var array<int, string> id zaznaczonych kont do akcji zbiorczych (zdjęcie bana, zdjęcie Inactive) */
    public array $checked = [];

    public function render(): View
    {
        return $this->view()->title(__('Users'));
    }

    /* ------------------------------------------------------------------
     | Dane
     * ----------------------------------------------------------------*/

    #[Computed]
    public function stats(): array
    {
        return [
            'users' => User::count(),
            'withoutRole' => User::doesntHave('roles')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'admins' => User::whereHas('roles', fn($q) => $q->where('name', self::ADMIN_ROLE))->count(),
        ];
    }

    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->get(['id', 'name']);
    }

    /** Konta pasujące do wyszukiwarki (imię, e-mail, nazwa drużyny) i filtra ról. */
    private function filtered()
    {
        return User::query()
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('team_name', 'like', $term));
            })
            ->when($this->roleFilter === '__none', fn($q) => $q->doesntHave('roles'))
            ->when($this->roleFilter === '__unverified', fn($q) => $q->whereNull('email_verified_at'))
            ->when($this->roleFilter !== '' && !str_starts_with($this->roleFilter, '__'), fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', $this->roleFilter)));
    }

    #[Computed]
    public function users()
    {
        return $this->filtered()
            ->with('roles:id,name')
            ->withCount('roles')
            ->orderBy($this->safeSortBy(), $this->safeDirection())
            ->paginate(10, pageName: 'usersPage');
    }

    /** Liczba kont pasujących do filtra (przycisk „zaznacz wszystkie z filtra”). */
    #[Computed]
    public function filteredCount(): int
    {
        return $this->filtered()->count();
    }

    /* ------------------------------------------------------------------
     | Sortowanie i filtry
     * ----------------------------------------------------------------*/

    private function sortableColumns(): array
    {
        return ['name', 'email', 'roles_count', 'created_at'];
    }

    private function safeSortBy(): string
    {
        return in_array($this->sortBy, $this->sortableColumns(), true) ? $this->sortBy : 'name';
    }

    private function safeDirection(): string
    {
        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }

    public function sort(string $column): void
    {
        if (!in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage('usersPage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('usersPage');
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage('usersPage');
        $this->checked = [];
    }

    /* ------------------------------------------------------------------
     | Akcje zbiorcze: zdjęcie bana albo statusu Inactive z zaznaczonych kont
     * ----------------------------------------------------------------*/

    /** Zaznacza wszystkie konta pasujące do filtra (ze wszystkich stron listy). */
    public function checkAllFiltered(): void
    {
        $this->checked = $this->filtered()->pluck('id')->map(fn($id) => (string) $id)->all();
    }

    public function clearChecked(): void
    {
        $this->checked = [];
    }

    /**
     * Zdejmuje rolę Banned albo Inactive z zaznaczonych kont. Konto bez innej roli dostaje User,
     * więc wraca do gry (nowy gracz trafia do Ligi podwórkowej, Roster::syncUser).
     */
    public function releaseRole(string $role): void
    {
        $this->authorizeAdmin();

        if (!in_array($role, \App\Support\Players::BLOCKING_ROLES, true)) {
            return;
        }

        $ids = array_map('intval', $this->checked);
        $users = User::whereIn('id', $ids)->whereHas('roles', fn($q) => $q->where('name', $role))->get();
        $added = 0;

        foreach ($users as $user) {
            $added += \App\Support\Office::liftRole($user, $role, __('Bulk action'));
        }

        $this->checked = [];
        unset($this->users, $this->stats, $this->filteredCount);

        $this->dispatch('ui-toast', text: __(':role removed from :count accounts.', ['role' => $role, 'count' => $users->count()]), variant: 'success');
        if ($added > 0) {
            $this->dispatch('ui-toast', text: __('Added to the season list: :count.', ['count' => $added]), variant: 'success');
        }
    }

    /* ------------------------------------------------------------------
     | Przypisywanie ról
     * ----------------------------------------------------------------*/

    public function edit(int $id): void
    {
        $this->authorizeAdmin();

        $user = User::with('roles:id,name')->findOrFail($id);

        $this->userId = $user->id;
        $this->userName = $user->name;
        $this->selected = $user->roles->pluck('name')->all();
        $this->resetValidation();

        $this->dispatch('ui-sheet', name: 'user-roles');
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'selected' => ['array'],
            'selected.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($this->userId);

        $hadAdmin = $user->hasRole(self::ADMIN_ROLE);
        $keepsAdmin = in_array(self::ADMIN_ROLE, $this->selected, true);

        if ($hadAdmin && !$keepsAdmin) {
            if ($user->is(auth()->user())) {
                $this->addError('selected', __('You cannot remove your own Admin role.'));

                return;
            }

            if (User::role(self::ADMIN_ROLE)->count() <= 1) {
                $this->addError('selected', __('At least one Admin must remain.'));

                return;
            }
        }

        // Przywrócenie nieaktywnego gracza: nadanie mu innej roli zdejmuje rolę Inactive,
        // inaczej dalej by blokowała grę (Players::BLOCKING_ROLES) i nie trafiłby na listę sezonu.
        $inactive = RoleName::Inactive->value;
        if ($user->hasRole($inactive) && in_array($inactive, $this->selected, true) && count($this->selected) > 1) {
            $this->selected = array_values(array_diff($this->selected, [$inactive]));
        }

        $wasPlayer = \App\Support\Players::canPlay($user);
        $wasBanned = $user->hasRole(RoleName::Banned->value);
        $wasInactive = $user->hasRole($inactive);

        $user->syncRoles($this->selected);
        $user->unsetRelation('roles');

        // Przywrócony nieaktywny gracz zaczyna serię kolejek bez typu od zera.
        if ($wasInactive && !$user->hasRole($inactive)) {
            $user->forceFill(['inactivity_reset_at' => now()])->save();
        }

        // Informacje systemowe: nowy gracz albo ban.
        if (!$wasPlayer && \App\Support\Players::canPlay($user)) {
            \App\Support\SystemFeed::record('players', ':name joined the game', ['name' => $user->team_name ?: $user->name], 'team.show', ['user' => $user->id], $user->id);
        }
        if (!$wasBanned && $user->hasRole(RoleName::Banned->value)) {
            \App\Support\SystemFeed::record('moderation', ':name was banned', ['name' => $user->name], null, [], $user->id);
        }

        // Rola decyduje o udziale w zabawie: gracz (bez bana) trafia na listy sezonów,
        // a zbanowany albo bez roli traci miejsce na rzecz bota (patrz App\Support\Roster).
        $roster = Roster::syncUser($user);

        $this->dispatch('ui-sheet', name: 'user-roles', open: false);
        $this->dispatch('ui-toast', text: __('Roles updated.'), variant: 'success');

        if ($roster['added'] > 0) {
            $this->dispatch('ui-toast', text: __('The player was added to the season list.'), variant: 'success');
        }

        if ($roster['released'] > 0) {
            $this->dispatch('ui-toast', text: __('The player was removed from the season list.'), variant: 'warning');
        }
        $this->resetForm();
    }

    /* ------------------------------------------------------------------
     | Potwierdzenie e-maila kodem: admin widzi kod, może wygenerować nowy albo potwierdzić ręcznie.
     * ----------------------------------------------------------------*/

    /** Nowy kod (bez wysyłki maila), np. gdy gracz dzwoni, że mail nie dotarł. */
    public function newCode(int $id): void
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $code = $user->newVerificationCode();
        $this->dispatch('ui-toast', text: __('New code for :name: :code', ['name' => $user->name, 'code' => $code]), variant: 'success');
    }

    /** Potwierdza adres bez kodu. */
    public function confirmEmail(int $id): void
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($id);

        if (!$user->hasVerifiedEmail()) {
            $user->confirmEmail();
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        unset($this->stats);
        $this->dispatch('ui-toast', text: __('Email of :name confirmed.', ['name' => $user->name]), variant: 'success');
    }

    public function resetForm(): void
    {
        $this->reset('userId', 'userName', 'selected');
        $this->resetValidation();
    }

    /* ------------------------------------------------------------------
     | Akcje Livewire to osobne żądania, więc sprawdzamy uprawnienia w każdej.
     * ----------------------------------------------------------------*/

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole(self::ADMIN_ROLE), 403);
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Nagłówek --}}
    <div>
        <x-ui.heading size="xl" level="1">{{ __('Users') }}</x-ui.heading>
        <x-ui.subheading>{{ __('Assign roles to the people who use this application.') }}</x-ui.subheading>
    </div>

    {{-- Statystyki --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="users" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('All users') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['users'] }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="shield-check" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Admins') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['admins'] }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="user-minus" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Without a role') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['withoutRole'] }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="envelope" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Email not confirmed') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['unverified'] }}</x-ui.heading>
            </div>
        </x-ui.card>
    </div>

    {{-- Wyszukiwarka i filtr --}}
    <div class="flex flex-wrap items-center gap-4">
        <div class="w-full sm:w-72">
            <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="__('Search by name, email or team...')" clearable />
        </div>

        <x-ui.filter-pills model="roleFilter" :value="$roleFilter" :label="__('Role')" class="w-full"
            :options="['' => __('All roles'), '__none' => __('Without a role'), '__unverified' => __('Email not confirmed')] + $this->roles->pluck('name', 'name')->all()" />
    </div>

    {{-- Akcje zbiorcze: zdjęcie bana i statusu Inactive z zaznaczonych kont --}}
    @php
        $checkedCount = count($checked);
    @endphp
    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-ui-line px-3 py-2 ">
        <x-ui.text class="me-2">{{ __('Selected: :count', ['count' => $checkedCount]) }}</x-ui.text>
        <x-ui.btn size="sm" variant="ghost" icon="check" wire:click="checkAllFiltered">
            {{ __('Select all matching (:count)', ['count' => $this->filteredCount]) }}
        </x-ui.btn>
        @if ($checkedCount > 0)
            <x-ui.btn size="sm" variant="ghost" icon="x-mark" wire:click="clearChecked">{{ __('Clear selection') }}</x-ui.btn>
            <x-ui.btn size="sm" icon="lock-open" x-on:click="$dispatch('confirm-action', { message: @js(__('Remove the ban from the selected accounts?')), run: () => $wire.releaseRole(@js(\App\Enums\RoleName::Banned->value)) })">{{ __('Remove ban') }}</x-ui.btn>
            <x-ui.btn size="sm" icon="arrow-uturn-left" x-on:click="$dispatch('confirm-action', { message: @js(__('Remove Inactive from the selected accounts?')), run: () => $wire.releaseRole(@js(\App\Enums\RoleName::Inactive->value)) })">{{ __('Remove Inactive') }}</x-ui.btn>
        @endif
    </div>

    {{-- Tabela użytkowników --}}
    <x-ui.table :paginate="$this->users">
        <x-ui.table.columns>
            <x-ui.table.column class="w-8" />
            <x-ui.table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                wire:click="sort('name')">
                {{ __('User') }}
            </x-ui.table.column>
            <x-ui.table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection"
                wire:click="sort('email')">
                {{ __('Email') }}
            </x-ui.table.column>
            <x-ui.table.column>{{ __('Email confirmation') }}</x-ui.table.column>
            <x-ui.table.column sortable :sorted="$sortBy === 'roles_count'" :direction="$sortDirection"
                wire:click="sort('roles_count')">
                {{ __('Roles') }}
            </x-ui.table.column>
            <x-ui.table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection"
                wire:click="sort('created_at')">
                {{ __('Joined') }}
            </x-ui.table.column>
            <x-ui.table.column />
        </x-ui.table.columns>

        <x-ui.table.rows>
            @forelse ($this->users as $user)
                <x-ui.table.row :key="'user-'.$user->id">
                    <x-ui.table.cell>
                        <input type="checkbox" wire:model.live="checked" value="{{ $user->id }}" aria-label="{{ __('Select') }}"
                            class="size-4 rounded border-zinc-300 text-lech-700 dark:border-zinc-600">
                    </x-ui.table.cell>
                    <x-ui.table.cell variant="strong">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar size="sm" :name="$user->name" />
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span>{{ $user->name }}</span>
                                    @if ($user->is(auth()->user()))
                                        <x-ui.badge size="sm" color="zinc">{{ __('You') }}</x-ui.badge>
                                    @endif
                                </div>
                                {{-- Nazwa drużyny z linkiem do profilu --}}
                                @if ($user->team_name)
                                    <a href="{{ route('team.show', $user) }}" target="_blank" class="block truncate text-xs font-normal text-ui-muted hover:text-lech-700 hover:underline">{{ $user->team_name }}</a>
                                @else
                                    <span class="block text-xs font-normal text-zinc-400">{{ __('No team name') }}</span>
                                @endif
                            </div>
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell class="text-ui-muted">{{ $user->email }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        @if ($user->email_verified_at)
                            <x-ui.badge size="sm" color="green" icon="check">{{ __('Confirmed') }}</x-ui.badge>
                        @else
                            @php
                                $codeValid = $user->verification_code && $user->verification_code_expires_at?->isFuture();
                            @endphp
                            <div class="flex items-center gap-1">
                                @if ($codeValid)
                                    <span class="rounded bg-amber-100 px-2 py-0.5 font-mono text-sm font-semibold tracking-widest text-amber-800 dark:bg-amber-500/20 dark:text-amber-200"
                                        title="{{ __('Valid until :time', ['time' => $user->verification_code_expires_at->format('d.m H:i')]) }}">{{ $user->verification_code }}</span>
                                @else
                                    <x-ui.badge size="sm" color="zinc">{{ $user->verification_code ? __('Code expired') : __('No code') }}</x-ui.badge>
                                @endif
                                <x-ui.btn variant="ghost" size="xs" icon="arrow-path" wire:click="newCode({{ $user->id }})"
                                    :aria-label="__('New code')" :tooltip="__('New code')" />
                                <x-ui.btn variant="ghost" size="xs" icon="check-badge" x-on:click="$dispatch('confirm-action', { message: @js(__('Confirm this email without a code?')), run: () => $wire.confirmEmail({{ $user->id }}) })" :aria-label="__('Confirm without a code')"
                                    :tooltip="__('Confirm without a code')" />
                            </div>
                        @endif
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($user->roles as $role)
                                <x-ui.badge size="sm"
                                    :color="\App\Enums\RoleName::tryFrom($role->name)?->color() ?? 'zinc'">
                                    {{ $role->name }}</x-ui.badge>
                            @empty
                                <span class="text-zinc-400">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell class="text-ui-muted">{{ $user->created_at?->format('Y-m-d') }}</x-ui.table.cell>
                    <x-ui.table.cell align="end">
                        <x-ui.btn variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                            wire:click="edit({{ $user->id }})" :aria-label="__('Edit roles')" />
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.row>
                    <x-ui.table.cell colspan="7" class="py-10 text-center text-ui-muted">
                        {{ __('No users match your filters.') }}
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforelse
        </x-ui.table.rows>
    </x-ui.table>

    {{-- Modal: role użytkownika --}}
    <x-ui.modal name="user-roles" class="w-full md:w-[30rem]" @close="$wire.resetForm()">
        <form wire:submit="save" class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ __('Edit roles') }}</x-ui.heading>
                <x-ui.text class="mt-1">{{ $userName }}</x-ui.text>
            </div>

            <div class="space-y-1">
                @if ($this->roles->isEmpty())
                    <x-ui.text>{{ __('No roles yet. Create them in Roles and permissions first.') }}</x-ui.text>
                @else
                    <x-ui.checkbox.group model="selected"
                        class="space-y-3 rounded-lg border border-ui-line p-4 ">
                        @foreach ($this->roles as $role)
                            <x-ui.checkbox wire:key="role-{{ $role->id }}" :value="$role->name"
                                :label="$role->name" />
                        @endforeach
                    </x-ui.checkbox.group>
                @endif

                <x-ui.error name="selected" />
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost" type="button">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" type="submit">
                    <span wire:loading.remove wire:target="save">{{ __('Save changes') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
                </x-ui.btn>
            </div>
        </form>
    </x-ui.modal>
</div>
