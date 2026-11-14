{{-- Formularz pozycji „Zasad gry” / „FAQ” w miejscu kliknięcia (panel Zasady i FAQ). Esc anuluje. --}}
<form wire:submit="save" class="space-y-3 p-4" x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())" @keydown.escape.prevent="$wire.cancel()">
    <x-ui.input wire:model="title" :label="__('Title')" maxlength="200" />
    <x-ui.textarea wire:model="body" :label="__('Content')" rows="8" />
    <div class="flex justify-end gap-2">
        <x-ui.btn variant="ghost" type="button" wire:click="cancel">{{ __('Cancel') }}</x-ui.btn>
        <x-ui.btn variant="primary" type="submit">
            <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
        </x-ui.btn>
    </div>
</form>
