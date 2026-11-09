<?php

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    /** Rola, której nie wolno usuwać ani zmieniać jej nazwy. */
    public const PROTECTED_ROLE = 'Admin';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'roles')]
    public string $tab = 'roles';

    #[Url(except: 'name')]
    public string $sortBy = 'name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    // Formularz roli
    public ?int $roleId = null;
    public string $roleName = '';
    /** @var array<int, string> nazwy zaznaczonych uprawnień */
    public array $selected = [];

    // Formularz uprawnienia
    public string $permissionName = '';

    // Usuwanie
    public ?int $deleteId = null;
    public string $deleteName = '';

    public function render(): View
    {
        return $this->view()->title(__('Roles and permissions'));
    }

    /* ------------------------------------------------------------------
     | Dane
     * ----------------------------------------------------------------*/

    #[Computed]
    public function stats(): array
    {
        return [
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'users' => User::has('roles')->count(),
        ];
    }

    #[Computed]
    public function roles()
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($this->safeSortBy(), $this->safeDirection())
            ->paginate(10, pageName: 'rolesPage');
    }

    #[Computed]
    public function permissions()
    {
        return Permission::query()
            ->withCount('roles')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($this->safeSortBy(), $this->safeDirection())
            ->paginate(10, pageName: 'permissionsPage');
    }

    /** Wszystkie uprawnienia pogrupowane po prefiksie przed kropką (np. roles.view -> roles). */
    #[Computed]
    public function groupedPermissions(): Collection
    {
        return Permission::orderBy('name')->get()
            ->groupBy(fn (Permission $p) => Str::contains($p->name, '.') ? Str::before($p->name, '.') : 'other');
    }

    /* ------------------------------------------------------------------
     | Sortowanie i wyszukiwanie
     * ----------------------------------------------------------------*/

    private function sortableColumns(): array
    {
        return $this->tab === 'roles'
            ? ['name', 'permissions_count', 'users_count', 'created_at']
            : ['name', 'roles_count', 'created_at'];
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
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPages();
    }

    public function updatedSearch(): void
    {
        $this->resetPages();
    }

    public function updatedTab(): void
    {
        $this->sortBy = 'name';
        $this->sortDirection = 'asc';
        $this->resetPages();
    }

    private function resetPages(): void
    {
        $this->resetPage('rolesPage');
        $this->resetPage('permissionsPage');
    }

    /* ------------------------------------------------------------------
     | Role
     * ----------------------------------------------------------------*/

    public function createRole(): void
    {
        $this->authorizeAdmin();
        $this->resetRoleForm();
        $this->dispatch('ui-sheet', name: 'role-form');
    }

    public function editRole(int $id): void
    {
        $this->authorizeAdmin();

        $role = Role::with('permissions')->findOrFail($id);

        $this->roleId = $role->id;
        $this->roleName = $role->name;
        $this->selected = $role->permissions->pluck('name')->all();
        $this->resetValidation();

        $this->dispatch('ui-sheet', name: 'role-form');
    }

    public function saveRole(): void
    {
        $this->authorizeAdmin();

        $role = $this->roleId ? Role::findOrFail($this->roleId) : null;
        $isProtected = $role?->name === self::PROTECTED_ROLE;

        // Nazwy chronionej roli nie zmieniamy, nawet gdy ktoś podmieni dane w żądaniu.
        if ($isProtected) {
            $this->roleName = $role->name;
        }

        $this->validate([
            'roleName' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($this->roleId)],
            'selected' => ['array'],
            'selected.*' => ['string', Rule::exists('permissions', 'name')],
        ], attributes: ['roleName' => __('Role name')]);

        $role ??= new Role(['guard_name' => 'web']);
        $role->name = $this->roleName;
        $role->save();
        $role->syncPermissions($this->selected);

        $this->dispatch('ui-sheet', name: 'role-form', open: false);
        $this->dispatch('ui-toast', text: __('Role saved.'), variant: 'success');
        $this->resetRoleForm();
    }

    public function toggleGroup(string $group): void
    {
        $names = $this->groupedPermissions->get($group, collect())->pluck('name')->all();

        $this->selected = count(array_diff($names, $this->selected)) === 0
            ? array_values(array_diff($this->selected, $names))
            : array_values(array_unique([...$this->selected, ...$names]));
    }

    public function resetRoleForm(): void
    {
        $this->reset('roleId', 'roleName', 'selected');
        $this->resetValidation();
    }

    /* ------------------------------------------------------------------
     | Uprawnienia
     * ----------------------------------------------------------------*/

    public function createPermission(): void
    {
        $this->authorizeAdmin();
        $this->reset('permissionName');
        $this->resetValidation();
        $this->dispatch('ui-sheet', name: 'permission-form');
    }

    public function savePermission(): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'permissionName' => [
                'required', 'string', 'max:80',
                'regex:/^[a-z0-9_-]+(\.[a-z0-9_-]+)*$/',
                Rule::unique('permissions', 'name'),
            ],
        ], attributes: ['permissionName' => __('Permission name')]);

        Permission::create(['name' => $this->permissionName, 'guard_name' => 'web']);

        $this->dispatch('ui-sheet', name: 'permission-form', open: false);
        $this->dispatch('ui-toast', text: __('Permission created.'), variant: 'success');
        $this->reset('permissionName');
    }

    /* ------------------------------------------------------------------
     | Usuwanie (role i uprawnienia)
     * ----------------------------------------------------------------*/

    public function confirmDeleteRole(int $id): void
    {
        $this->authorizeAdmin();
        $role = Role::withCount('users')->findOrFail($id);

        if ($role->name === self::PROTECTED_ROLE) {
            $this->dispatch('ui-toast', text: __('The :name role cannot be deleted.', ['name' => $role->name]), variant: 'danger');

            return;
        }

        if ($role->users_count > 0) {
            $this->dispatch('ui-toast', text: __('Remove this role from its users first.'), variant: 'warning');

            return;
        }

        $this->deleteId = $role->id;
        $this->deleteName = $role->name;
        $this->dispatch('ui-sheet', name: 'delete-role');
    }

    public function deleteRole(): void
    {
        $this->authorizeAdmin();
        $role = Role::withCount('users')->findOrFail($this->deleteId);

        abort_if($role->name === self::PROTECTED_ROLE || $role->users_count > 0, 403);

        $role->delete();

        $this->reset('deleteId', 'deleteName');
        $this->dispatch('ui-sheet', name: 'delete-role', open: false);
        $this->dispatch('ui-toast', text: __('Role deleted.'), variant: 'success');
    }

    public function confirmDeletePermission(int $id): void
    {
        $this->authorizeAdmin();
        $permission = Permission::findOrFail($id);

        $this->deleteId = $permission->id;
        $this->deleteName = $permission->name;
        $this->dispatch('ui-sheet', name: 'delete-permission');
    }

    public function deletePermission(): void
    {
        $this->authorizeAdmin();

        Permission::findOrFail($this->deleteId)->delete();

        $this->reset('deleteId', 'deleteName');
        $this->dispatch('ui-sheet', name: 'delete-permission', open: false);
        $this->dispatch('ui-toast', text: __('Permission deleted.'), variant: 'success');
    }

    /* ------------------------------------------------------------------
     | Autoryzacja: akcje Livewire to osobne żądania, więc sprawdzamy w każdej.
     * ----------------------------------------------------------------*/

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole(self::PROTECTED_ROLE), 403);
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Nagłówek --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Roles and permissions') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Control what each group of users can do.') }}</x-ui.subheading>
        </div>

        @if ($tab === 'roles')
            <x-ui.btn variant="primary" icon="plus" wire:click="createRole">{{ __('New role') }}</x-ui.btn>
        @else
            <x-ui.btn variant="primary" icon="plus" wire:click="createPermission">{{ __('New permission') }}</x-ui.btn>
        @endif
    </div>

    {{-- Statystyki --}}
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="shield-check" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Roles') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['roles'] }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="key" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Permissions') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['permissions'] }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="users" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Users with a role') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['users'] }}</x-ui.heading>
            </div>
        </x-ui.card>
    </div>

    {{-- Zakładki + wyszukiwarka --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
<x-ui.btn.group>
    <x-ui.btn
        icon="shield-check"
        :variant="$tab === 'roles' ? 'filled' : 'ghost'"
        wire:click="$set('tab', 'roles')"
    >
        {{ __('Roles') }}
    </x-ui.btn>
    <x-ui.btn
        icon="key"
        :variant="$tab === 'permissions' ? 'filled' : 'ghost'"
        wire:click="$set('tab', 'permissions')"
    >
        {{ __('Permissions') }}
    </x-ui.btn>
</x-ui.btn.group>

        <div class="w-full sm:w-72">
            <x-ui.input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="$tab === 'roles' ? __('Search roles...') : __('Search permissions...')"
                clearable
            />
        </div>
    </div>

    {{-- Tabela ról --}}
    @if ($tab === 'roles')
        <x-ui.table :paginate="$this->roles">
            <x-ui.table.columns>
                <x-ui.table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                    {{ __('Role') }}
                </x-ui.table.column>
                <x-ui.table.column sortable :sorted="$sortBy === 'permissions_count'" :direction="$sortDirection" wire:click="sort('permissions_count')">
                    {{ __('Permissions') }}
                </x-ui.table.column>
                <x-ui.table.column sortable :sorted="$sortBy === 'users_count'" :direction="$sortDirection" wire:click="sort('users_count')">
                    {{ __('Users') }}
                </x-ui.table.column>
                <x-ui.table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                    {{ __('Created') }}
                </x-ui.table.column>
                <x-ui.table.column />
            </x-ui.table.columns>

            <x-ui.table.rows>
                @forelse ($this->roles as $role)
                    <x-ui.table.row :key="'role-'.$role->id">
                        <x-ui.table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                {{ $role->name }}
                                @if ($role->name === 'Admin')
                                    <x-ui.badge size="sm" color="amber" icon="lock-closed">{{ __('Protected') }}</x-ui.badge>
                                @endif
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            <x-ui.badge size="sm" color="blue">{{ $role->permissions_count }}</x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell>{{ $role->users_count }}</x-ui.table.cell>
                        <x-ui.table.cell class="text-ui-muted">{{ $role->created_at?->format('Y-m-d') }}</x-ui.table.cell>
                        <x-ui.table.cell align="end">
                            <x-ui.dropdown position="bottom" align="end">
                                <x-ui.btn variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <x-ui.menu>
                                    <x-ui.menu.item icon="pencil-square" wire:click="editRole({{ $role->id }})">{{ __('Edit') }}</x-ui.menu.item>
                                    @if ($role->name !== 'Admin')
                                        <x-ui.menu.separator />
                                        <x-ui.menu.item icon="trash" variant="danger" wire:click="confirmDeleteRole({{ $role->id }})">{{ __('Delete') }}</x-ui.menu.item>
                                    @endif
                                </x-ui.menu>
                            </x-ui.dropdown>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="5" class="py-10 text-center text-ui-muted">
                            {{ $search !== '' ? __('No roles match your search.') : __('No roles yet. Create the first one.') }}
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table.rows>
        </x-ui.table>
    @endif

    {{-- Tabela uprawnień --}}
    @if ($tab === 'permissions')
        <x-ui.table :paginate="$this->permissions">
            <x-ui.table.columns>
                <x-ui.table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                    {{ __('Permission') }}
                </x-ui.table.column>
                <x-ui.table.column sortable :sorted="$sortBy === 'roles_count'" :direction="$sortDirection" wire:click="sort('roles_count')">
                    {{ __('Used in roles') }}
                </x-ui.table.column>
                <x-ui.table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                    {{ __('Created') }}
                </x-ui.table.column>
                <x-ui.table.column />
            </x-ui.table.columns>

            <x-ui.table.rows>
                @forelse ($this->permissions as $permission)
                    <x-ui.table.row :key="'permission-'.$permission->id">
                        <x-ui.table.cell variant="strong">
                            <code class="text-sm">{{ $permission->name }}</code>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            <x-ui.badge size="sm" :color="$permission->roles_count ? 'blue' : 'zinc'">{{ $permission->roles_count }}</x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="text-ui-muted">{{ $permission->created_at?->format('Y-m-d') }}</x-ui.table.cell>
                        <x-ui.table.cell align="end">
                            <x-ui.btn
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                inset="top bottom"
                                wire:click="confirmDeletePermission({{ $permission->id }})"
                                :aria-label="__('Delete')"
                            />
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="4" class="py-10 text-center text-ui-muted">
                            {{ $search !== '' ? __('No permissions match your search.') : __('No permissions yet. Create the first one.') }}
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table.rows>
        </x-ui.table>
    @endif

    {{-- Modal: rola --}}
    <x-ui.modal name="role-form" class="w-full md:w-[36rem]" @close="$wire.resetRoleForm()">
        <form wire:submit="saveRole" class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ $roleId ? __('Edit role') : __('New role') }}</x-ui.heading>
                <x-ui.text class="mt-1">{{ __('Choose a name and the permissions this role grants.') }}</x-ui.text>
            </div>

            <x-ui.input
                wire:model="roleName"
                :label="__('Role name')"
                :disabled="$roleName === 'Admin' && $roleId"
                autofocus
            />

            <div class="space-y-1">
                <x-ui.label>{{ __('Permissions') }}</x-ui.label>

                @if ($this->groupedPermissions->isEmpty())
                    <x-ui.text>{{ __('No permissions yet. Create them in the Permissions tab first.') }}</x-ui.text>
                @else
                    <x-ui.checkbox.group model="selected" class="max-h-80 space-y-4 overflow-y-auto rounded-lg border border-ui-line p-4 ">
                        @foreach ($this->groupedPermissions as $group => $items)
                            <div wire:key="group-{{ $group }}" class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <x-ui.heading>{{ \Illuminate\Support\Str::headline($group) }}</x-ui.heading>
                                    <x-ui.btn size="xs" variant="subtle" type="button" wire:click="toggleGroup('{{ $group }}')">
                                        {{ __('Toggle all') }}
                                    </x-ui.btn>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($items as $permission)
                                        <x-ui.checkbox
                                            wire:key="perm-{{ $permission->id }}"
                                            :value="$permission->name"
                                            :label="$permission->name"
                                        />
                                    @endforeach
                                </div>
                            </div>
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
                    <span wire:loading.remove wire:target="saveRole">{{ __('Save changes') }}</span>
                    <span wire:loading wire:target="saveRole">{{ __('Saving...') }}</span>
                </x-ui.btn>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal: uprawnienie --}}
    <x-ui.modal name="permission-form" class="w-full md:w-[28rem]">
        <form wire:submit="savePermission" class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ __('New permission') }}</x-ui.heading>
                <x-ui.text class="mt-1">{{ __('Use the format group.action, for example roles.view.') }}</x-ui.text>
            </div>

            <x-ui.input wire:model="permissionName" :label="__('Permission name')" placeholder="roles.view" autofocus />

            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost" type="button">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" type="submit">{{ __('Create permission') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal: usuń rolę --}}
    <x-ui.modal name="delete-role" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ __('Delete role?') }}</x-ui.heading>
                <x-ui.text class="mt-2">
                    {{ __('The role :name will be permanently deleted.', ['name' => $deleteName]) }}
                </x-ui.text>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="deleteRole">{{ __('Delete role') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>

    {{-- Modal: usuń uprawnienie --}}
    <x-ui.modal name="delete-permission" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ __('Delete permission?') }}</x-ui.heading>
                <x-ui.text class="mt-2">
                    {{ __('The permission :name will be removed from every role that uses it.', ['name' => $deleteName]) }}
                </x-ui.text>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="deletePermission">{{ __('Delete permission') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
</div>