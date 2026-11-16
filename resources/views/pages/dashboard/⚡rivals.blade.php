<?php

use App\Enums\Permission;
use App\Models\Rival;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rywale Lecha (16.11.2026): nazwa jak w kalendarzu meczów, skrót i 1–3 barwy. Nazwa podpowiada się przy dodawaniu
 * meczu (kalendarz Lecha, kolejki), a barwy pokazuje tablica meczu na stronie głównej (App\Models\Rival::colorsFor).
 * Podgląd: season-list, zmiany: season-edit.
 */
new class extends Component {
    /** Rywal w edycji: null = brak formularza, 0 = nowy. */
    public ?int $editId = null;

    public string $name = '';

    public string $shortName = '';

    /** @var list<string> */
    public array $colors = [];

    public function render(): View
    {
        return $this->view()->title(__('Rivals'));
    }

    #[Computed]
    public function rivals()
    {
        return Rival::orderBy('name')->get();
    }

    #[Computed]
    public function canEdit(): bool
    {
        return (bool) auth()->user()?->can(Permission::SeasonEdit->value);
    }

    public function add(): void
    {
        $this->authorizeEdit();
        $this->resetValidation();
        $this->editId = 0;
        $this->name = '';
        $this->shortName = '';
        $this->colors = ['#ffffff', '#000000'];
    }

    public function edit(int $id): void
    {
        $this->authorizeEdit();
        $this->resetValidation();
        $rival = Rival::findOrFail($id);
        $this->editId = $rival->id;
        $this->name = $rival->name;
        $this->shortName = (string) $rival->short_name;
        $this->colors = array_values($rival->colors ?? []);
    }

    public function addColor(): void
    {
        if (count($this->colors) < 3) {
            $this->colors[] = '#888888';
        }
    }

    public function removeColor(int $index): void
    {
        unset($this->colors[$index]);
        $this->colors = array_values($this->colors);
    }

    public function save(): void
    {
        $this->authorizeEdit();
        $this->name = Str::squish($this->name);
        $this->shortName = Str::squish($this->shortName);
        $this->colors = array_values(array_map(fn ($c) => mb_strtolower((string) $c), $this->colors));

        $this->validate([
            'name' => ['required', 'string', 'max:80', 'unique:rivals,name'.($this->editId ? ','.$this->editId : '')],
            'shortName' => ['nullable', 'string', 'max:12'],
            'colors' => ['array', 'max:3'],
            'colors.*' => ['regex:/^#[0-9a-f]{6}$/'],
        ], [], ['name' => __('Club name'), 'shortName' => __('Short name'), 'colors.*' => __('Colour')]);

        $data = ['name' => $this->name, 'short_name' => $this->shortName ?: null, 'colors' => $this->colors];
        $this->editId ? Rival::findOrFail($this->editId)->update($data) : Rival::create($data);

        Audit::log('rivals.saved', null, [], $data, $this->name);
        $this->cancel();
        unset($this->rivals);
        $this->dispatch('ui-toast', text: __('Saved.'), variant: 'success');
    }

    public function cancel(): void
    {
        $this->reset('editId', 'name', 'shortName', 'colors');
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        $this->authorizeEdit();
        $rival = Rival::findOrFail($id);
        Audit::log('rivals.deleted', null, ['name' => $rival->name], [], $rival->name);
        $rival->delete();
        unset($this->rivals);
        $this->dispatch('ui-toast', text: __('Deleted.'), variant: 'success');
    }

    private function authorizeEdit(): void
    {
        abort_unless($this->canEdit, 403);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Rivals') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Lech opponents with their colours. The name must be the same as in the match calendar: then the colours show on the match board on the home page.') }}</x-ui.subheading>
        </div>
        @if ($this->canEdit)
            <x-ui.btn variant="primary" icon="plus" wire:click="add">{{ __('Add a rival') }}</x-ui.btn>
        @endif
    </div>

    @if ($editId !== null)
        <form wire:submit="save" class="space-y-4 rounded-2xl border border-club/40 bg-ui-surface p-5 shadow-sm" x-on:keydown.escape="$wire.cancel()">
            <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                <x-ui.input wire:model="name" :label="__('Club name')" placeholder="GKS Katowice" maxlength="80" />
                <x-ui.input wire:model="shortName" :label="__('Short name')" placeholder="GKS" maxlength="12" />
            </div>
            <div>
                <p class="mb-2 text-sm font-medium">{{ __('Colours (1 to 3, from the left)') }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    @foreach ($colors as $index => $color)
                        <div wire:key="color-{{ $index }}" class="flex items-center gap-1.5 rounded-xl border border-ui-line px-2 py-1.5">
                            <input type="color" wire:model.live="colors.{{ $index }}" class="size-9 cursor-pointer rounded-lg border-0 bg-transparent p-0" aria-label="{{ __('Colour') }} {{ $index + 1 }}">
                            <span class="font-mono text-xs uppercase text-ui-muted">{{ $color }}</span>
                            <button type="button" wire:click="removeColor({{ $index }})" class="grid size-6 place-items-center rounded-full text-ui-muted hover:bg-ui-raised" aria-label="{{ __('Delete') }}">
                                <x-ui.icon name="x-mark" variant="micro" />
                            </button>
                        </div>
                    @endforeach
                    @if (count($colors) < 3)
                        <x-ui.btn type="button" size="sm" variant="ghost" icon="plus" wire:click="addColor">{{ __('Add a colour') }}</x-ui.btn>
                    @endif
                </div>
                <x-ui.error name="colors.*" />
                @if ($colors !== [])
                    <div class="mt-3 h-3 w-48 rounded-full" style="{{ \App\Models\Rival::stripe($colors) }}"></div>
                @endif
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.btn variant="ghost" type="button" wire:click="cancel">{{ __('Cancel') }}</x-ui.btn>
                <x-ui.btn variant="primary" type="submit">{{ __('Save') }}</x-ui.btn>
            </div>
        </form>
    @endif

    @if ($this->rivals->isEmpty())
        <x-ui.callout icon="information-circle">{{ __('No rivals yet.') }}</x-ui.callout>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->rivals as $rival)
                <div wire:key="rival-{{ $rival->id }}" class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface shadow-sm">
                    <div class="h-2" style="{{ \App\Models\Rival::stripe($rival->colors ?? []) }}"></div>
                    <div class="flex items-center gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $rival->name }}</p>
                            <p class="text-xs text-ui-muted">{{ $rival->short_name ?: '—' }}</p>
                        </div>
                        @if ($this->canEdit)
                            <x-ui.btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $rival->id }})" :aria-label="__('Edit')" />
                            <x-ui.btn variant="ghost" size="sm" icon="trash" wire:click="delete({{ $rival->id }})" wire:confirm="{{ __('Delete this rival?') }}" :aria-label="__('Delete')" />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
