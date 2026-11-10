<?php

use App\Enums\Permission;
use App\Models\Bot;
use App\Support\Audit;
use App\Support\BotNames;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Boty: pula 512 zespołów bez właścicieli. Admin zmienia nazwy wyświetlane w tabelach.
 * Domyślne nazwy pochodzą z App\Support\BotNames (BotsSeeder).
 * Uprawnienia: podgląd season-list, zmiana nazwy season-edit.
 */
new class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    // --- formularz zmiany nazwy ---
    public ?int $botId = null;
    public int $botSlot = 0;
    public string $name = '';

    public function render(): View
    {
        return $this->view()->title(__('Bots'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage('botsPage');
    }

    /* ==================================================================
     | DANE DO WIDOKU
     * ================================================================*/

    /** Liczba botów w bazie i ile ma zmienioną nazwę. */
    #[Computed]
    public function stats(): array
    {
        $bots = Bot::get(['sort_order', 'name']);

        return [
            'total' => $bots->count(),
            'renamed' => $bots->filter(fn(Bot $bot) => $bot->isRenamed())->count(),
        ];
    }

    #[Computed]
    public function bots()
    {
        return Bot::query()
            ->when($this->search !== '', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('sort_order')
            ->paginate(25, pageName: 'botsPage');
    }

    /* ==================================================================
     | ZMIANA NAZWY
     * ================================================================*/

    public function edit(int $id): void
    {
        $this->authorizeAbility(Permission::SeasonEdit);

        $bot = Bot::findOrFail($id);

        $this->botId = $bot->id;
        $this->botSlot = $bot->sort_order;
        $this->name = $bot->name;
        $this->resetValidation();

        $this->dispatch('ui-sheet', name: 'bot-form');
    }

    protected function rules(): array
    {
        return [
            // Wspólne reguły z propozycjami nazw od graczy (BotNames::rules).
            'name' => BotNames::rules($this->botId),
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => __('Bot name')];
    }

    public function save(): void
    {
        $this->authorizeAbility(Permission::SeasonEdit);

        $bot = Bot::findOrFail($this->botId);

        $this->name = Str::squish($this->name);
        $this->validate();

        $this->rename($bot, $this->name);

        $this->dispatch('ui-sheet', name: 'bot-form', open: false);
        $this->resetForm();

        $this->dispatch('ui-toast', text: __('Bot name saved.'), variant: 'success');
    }

    /** Przywraca domyślną nazwę z BotNames, o ile nie zajął jej inny bot. */
    public function resetName(int $id): void
    {
        $this->authorizeAbility(Permission::SeasonEdit);

        $bot = Bot::findOrFail($id);
        $default = $bot->defaultName();

        if ($default === null || $default === $bot->name) {
            return;
        }

        if (Bot::where('name', $default)->where('id', '!=', $bot->id)->exists()) {
            $this->dispatch('ui-toast', text: __('The default name is used by another bot.'), variant: 'warning');

            return;
        }

        $this->rename($bot, $default);

        $this->dispatch('ui-toast', text: __('Default name restored.'), variant: 'success');
    }

    /** Wywoływana także przez @close modala, więc jest publiczna. */
    public function resetForm(): void
    {
        $this->reset('botId', 'botSlot', 'name');
        $this->resetValidation();
    }

    /* ==================================================================
     | POMOCNICZE
     * ================================================================*/

    private function rename(Bot $bot, string $newName): void
    {
        $old = $bot->name;

        if ($old === $newName) {
            return;
        }

        $bot->update(['name' => $newName]);

        Audit::log('bot.renamed', null, ['name' => $old], ['name' => $newName], __('Bot') . ' #' . $bot->sort_order);

        unset($this->bots, $this->stats);
    }

    /** Akcje Livewire to osobne żądania, więc uprawnienie sprawdzamy w każdej z nich. */
    private function authorizeAbility(Permission $permission): void
    {
        abort_unless(auth()->user()?->can($permission->value), 403);
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Nagłówek --}}
    <div>
        <x-ui.heading size="xl" level="1">{{ __('Bots') }}</x-ui.heading>
        <x-ui.subheading>{{ __('Bots fill the empty places on the team list. Here you can change their names.') }}
        </x-ui.subheading>
    </div>

    {{-- Statystyki --}}
    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="cpu-chip" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Bots in the database') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['total'] }} / {{ BotNames::COUNT }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="pencil-square" class="size-8 text-zinc-400" />
            <div>
                <x-ui.text>{{ __('Renamed bots') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['renamed'] }}</x-ui.heading>
            </div>
        </x-ui.card>
    </div>

    @if ($this->stats['total'] < BotNames::COUNT)
        <x-ui.text class="text-amber-600 dark:text-amber-400">
            {{ __('Some bots are missing. Run: php artisan db:seed --class=BotsSeeder') }}
        </x-ui.text>
    @endif

    {{-- Wyszukiwarka --}}
    <div class="w-full sm:w-72">
        <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            :placeholder="__('Search by name...')" clearable />
    </div>

    {{-- Tabela --}}
    <x-ui.table :paginate="$this->bots">
        <x-ui.table.columns>
            <x-ui.table.column>#</x-ui.table.column>
            <x-ui.table.column>{{ __('Bot name') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Status') }}</x-ui.table.column>
            <x-ui.table.column />
        </x-ui.table.columns>

        <x-ui.table.rows>
            @forelse ($this->bots as $bot)
                <x-ui.table.row :key="'bot-'.$bot->id">
                    <x-ui.table.cell variant="strong">{{ $bot->sort_order }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        <x-ui.link :href="route('bot.show', $bot)" wire:navigate>{{ $bot->name }}</x-ui.link>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        @if ($bot->isRenamed())
                            <x-ui.badge size="sm" color="amber">{{ __('Renamed') }}</x-ui.badge>
                        @else
                            <x-ui.badge size="sm" color="zinc">{{ __('Default') }}</x-ui.badge>
                        @endif
                        @if (! $bot->is_active)
                            <x-ui.badge size="sm" color="red" :title="__('Beyond place 512 of the list: rests this season, can replace a banned player')">{{ __('Resting') }}</x-ui.badge>
                        @endif
                    </x-ui.table.cell>
                    <x-ui.table.cell align="end">
                        @can(\App\Enums\Permission::SeasonEdit->value)
                            <div class="flex items-center justify-end gap-1">
                                @if ($bot->isRenamed())
                                    <x-ui.btn variant="ghost" size="sm" icon="arrow-uturn-left" inset="top bottom"
                                        wire:click="resetName({{ $bot->id }})" :aria-label="__('Restore default name')" />
                                @endif
                                <x-ui.btn variant="ghost" size="sm" icon="pencil-square" inset="top bottom"
                                    wire:click="edit({{ $bot->id }})" :aria-label="__('Edit')" />
                            </div>
                        @endcan
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.row>
                    <x-ui.table.cell colspan="4" class="py-10 text-center text-ui-muted">
                        {{ __('No bots match your search.') }}
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforelse
        </x-ui.table.rows>
    </x-ui.table>

    {{-- Modal: zmiana nazwy bota --}}
    <x-ui.modal name="bot-form" class="w-full md:w-[28rem]" @close="$wire.resetForm()">
        <form wire:submit="save" class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ __('Edit bot name') }}</x-ui.heading>
                <x-ui.text class="mt-1">{{ __('Bot') }} #{{ $botSlot }}</x-ui.text>
            </div>

            <x-ui.input wire:model="name" :label="__('Bot name')" maxlength="40" />

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