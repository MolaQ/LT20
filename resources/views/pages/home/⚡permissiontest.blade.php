<?php

use App\Enums\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission as PermissionModel;

// Jeśli nie masz jeszcze layoutu publicznego, usuń atrybut #[Layout]
// (komponent użyje wtedy domyślnego layoutu aplikacji).
new #[Layout('layouts::public')] class extends Component {
    public function render(): View
    {
        return $this->view()->title(config('app.name'));
    }

    /**
     * Raport porównujący enum Permission z tym, co użytkownik faktycznie posiada.
     * Zwraca null dla gościa.
     */
    #[Computed]
    public function report(): ?array
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $user->loadMissing('roles.permissions', 'permissions');

        $inDatabase = PermissionModel::pluck('name')->all();
        $direct = $user->permissions->pluck('name')->all();

        // nazwa uprawnienia => lista ról, które je dają
        $viaRoles = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $viaRoles[$permission->name][] = $role->name;
            }
        }

        $rows = collect(Permission::cases())->map(function (Permission $case) use ($inDatabase, $direct, $viaRoles) {
            $name = $case->value;

            return [
                'case' => $case->name,
                'value' => $name,
                'group' => Str::headline(Str::before($name, '-')),
                'inDatabase' => in_array($name, $inDatabase, true),
                'granted' => isset($viaRoles[$name]) || in_array($name, $direct, true),
                'viaRoles' => $viaRoles[$name] ?? [],
                'direct' => in_array($name, $direct, true),
            ];
        });

        $allGranted = array_unique([...array_keys($viaRoles), ...$direct]);

        return [
            'user' => $user,
            'roles' => $user->roles->pluck('name')->all(),
            'rows' => $rows,
            'granted' => $rows->where('granted', true)->count(),
            'missing' => $rows->where('granted', false),
            'notInDatabase' => $rows->where('inDatabase', false),
            // uprawnienia użytkownika, których nie ma w enumie (rozjazd kod/baza)
            'outsideEnum' => array_values(array_diff($allGranted, Permission::values())),
        ];
    }
};
?>

<div class="mx-auto w-full max-w-4xl space-y-6 py-10">

    <div>
        <x-ui.heading size="xl" level="1">{{ __('Access check') }}</x-ui.heading>
        <x-ui.subheading>{{ __('A quick view of what the signed-in user can do.') }}</x-ui.subheading>
    </div>

    @if (! $this->report)
        <x-ui.card class="space-y-4">
            <x-ui.heading>{{ __('You are not signed in') }}</x-ui.heading>
            <x-ui.text>{{ __('Sign in to see your roles and permissions.') }}</x-ui.text>
            <div>
                <x-ui.btn variant="primary" :href="route('login')">{{ __('Log in') }}</x-ui.btn>
            </div>
        </x-ui.card>
    @else
        @php($report = $this->report)

        {{-- Użytkownik i role --}}
        <x-ui.card class="space-y-4">
            <div class="flex items-center gap-4">
                <x-ui.avatar :name="$report['user']->name" />
                <div>
                    <x-ui.heading>{{ $report['user']->name }}</x-ui.heading>
                    <x-ui.text>{{ $report['user']->email }}</x-ui.text>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.text>{{ __('Roles') }}:</x-ui.text>
                @forelse ($report['roles'] as $role)
                    <x-ui.badge color="blue">{{ $role }}</x-ui.badge>
                @empty
                    <x-ui.badge color="zinc">{{ __('None') }}</x-ui.badge>
                @endforelse
            </div>
        </x-ui.card>

        {{-- Podsumowanie --}}
        <div class="grid gap-4 md:grid-cols-3">
            <x-ui.card class="flex items-center gap-4">
                <x-ui.icon name="check-circle" class="size-8 text-green-500" />
                <div>
                    <x-ui.text>{{ __('Granted') }}</x-ui.text>
                    <x-ui.heading size="xl">{{ $report['granted'] }} / {{ $report['rows']->count() }}</x-ui.heading>
                </div>
            </x-ui.card>
            <x-ui.card class="flex items-center gap-4">
                <x-ui.icon name="x-circle" class="size-8 text-zinc-400" />
                <div>
                    <x-ui.text>{{ __('Missing') }}</x-ui.text>
                    <x-ui.heading size="xl">{{ $report['missing']->count() }}</x-ui.heading>
                </div>
            </x-ui.card>
            <x-ui.card class="flex items-center gap-4">
                <x-ui.icon name="exclamation-triangle" class="size-8 text-amber-500" />
                <div>
                    <x-ui.text>{{ __('Not in database') }}</x-ui.text>
                    <x-ui.heading size="xl">{{ $report['notInDatabase']->count() }}</x-ui.heading>
                </div>
            </x-ui.card>
        </div>

        @if ($report['notInDatabase']->isNotEmpty())
            <x-ui.card class="space-y-2 border-amber-300 dark:border-amber-500/50">
                <x-ui.heading>{{ __('Some permissions from the code are missing in the database') }}</x-ui.heading>
                <x-ui.text>{{ __('Run the seeder to create them:') }} <code>php artisan db:seed --class=RolesAndPermissionsSeeder</code></x-ui.text>
            </x-ui.card>
        @endif

        {{-- Tabela wszystkich uprawnień z enuma --}}
        <x-ui.table>
            <x-ui.table.columns>
                <x-ui.table.column>{{ __('Group') }}</x-ui.table.column>
                <x-ui.table.column>{{ __('Permission') }}</x-ui.table.column>
                <x-ui.table.column>{{ __('Status') }}</x-ui.table.column>
                <x-ui.table.column>{{ __('Source') }}</x-ui.table.column>
            </x-ui.table.columns>

            <x-ui.table.rows>
                @foreach ($report['rows'] as $row)
                    <x-ui.table.row :key="$row['value']">
                        <x-ui.table.cell class="text-zinc-500">{{ $row['group'] }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            <code class="text-sm">{{ $row['value'] }}</code>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            @if ($row['granted'])
                                <x-ui.badge size="sm" color="green" icon="check">{{ __('Granted') }}</x-ui.badge>
                            @else
                                <x-ui.badge size="sm" color="zinc" icon="x-mark">{{ __('Missing') }}</x-ui.badge>
                            @endif

                            @unless ($row['inDatabase'])
                                <x-ui.badge size="sm" color="amber">{{ __('Not in database') }}</x-ui.badge>
                            @endunless
                        </x-ui.table.cell>
                        <x-ui.table.cell class="text-zinc-500">
                            @if ($row['viaRoles'])
                                {{ __('Role') }}: {{ implode(', ', $row['viaRoles']) }}
                            @endif
                            @if ($row['direct'])
                                {{ __('Direct') }}
                            @endif
                            @if (! $row['granted'])
                                &ndash;
                            @endif
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table.rows>
        </x-ui.table>

        @if ($report['outsideEnum'])
            <x-ui.card class="space-y-2">
                <x-ui.heading>{{ __('Permissions outside the Permission enum') }}</x-ui.heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($report['outsideEnum'] as $name)
                        <x-ui.badge size="sm" color="amber">{{ $name }}</x-ui.badge>
                    @endforeach
                </div>
            </x-ui.card>
        @endif
    @endif
</div>
