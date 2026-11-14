{{--
    Poprawka treści pytania z banku w miejscu, gdzie admin je zobaczył (zestaw kolejki, bank, lista banku).
    Zapis: saveTemplate (zmiana trafia też do pytań kolejek bez odpowiedzi). Esc anuluje.
--}}
<form wire:submit="saveTemplate" x-on:keydown.escape="$wire.cancelEdit()"
    class="flex w-full flex-wrap items-start gap-2 rounded-xl border border-club/40 bg-club/5 p-2">
    <div class="min-w-0 flex-1">
        <x-ui.input wire:model="editText" x-init="$nextTick(() => { $el.focus(); $el.select?.() })" />
        <x-ui.error name="editText" />
    </div>
    <x-ui.input wire:model="editCategory" list="question-categories" :placeholder="__('Category')" class="w-44" />
    <x-ui.btn type="submit" variant="primary" size="sm">{{ __('Save') }}</x-ui.btn>
    <x-ui.btn type="button" size="sm" wire:click="cancelEdit">{{ __('Cancel') }}</x-ui.btn>
</form>
