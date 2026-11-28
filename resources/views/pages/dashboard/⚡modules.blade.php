<?php

use App\Enums\Permission;
use App\Support\Audit;
use App\Support\Modules;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Moduły gry (App\Support\Modules): przełączniki włączające i wyłączające całe funkcje, np. sparingi. */
new class extends Component {
    /** @var array<string, bool> */
    public array $enabled = [];

    public function mount(): void
    {
        $this->enabled = Modules::all();
    }

    public function render(): View
    {
        return $this->view()->title(__('Modules'));
    }

    public function updatedEnabled(mixed $value, string $module): void
    {
        abort_unless(auth()->user()?->can(Permission::SeasonEdit->value), 403);

        $before = Modules::enabled($module);
        Modules::set($module, (bool) $value);
        Audit::log('modules.toggled', null, [$module => $before], [$module => (bool) $value], __('Modules'));
        $this->enabled = Modules::all();
        $this->dispatch('ui-toast', variant: 'success', text: __('Settings saved.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <x-ui.heading size="xl" level="1">{{ __('Modules') }}</x-ui.heading>
        <x-ui.subheading>{{ __('Turn whole game features on or off for all players.') }}</x-ui.subheading>
    </div>

    @php $canEdit = auth()->user()->can(\App\Enums\Permission::SeasonEdit->value); @endphp

    <x-ui.card class="max-w-2xl space-y-4">
        @foreach (Modules::labels() as $module => [$label, $description])
            <x-ui.switch wire:model.live="enabled.{{ $module }}" :label="$label" :description="$description" :disabled="! $canEdit" wire:key="module-{{ $module }}" />
        @endforeach
    </x-ui.card>
</div>
