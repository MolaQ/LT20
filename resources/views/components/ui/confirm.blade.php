{{--
    Okno potwierdzenia skorupy 2.0 (zamiast x-confirm-dialog z Flux), ten sam sposób użycia:
    x-on:click="$dispatch('confirm-action', { message: @js(__('...')), run: () => $wire.akcja(1) })", opcjonalnie confirm i danger.
--}}
<div x-data="{ message: '', confirmLabel: '', danger: false, run: null }"
    x-on:confirm-action.window="message = $event.detail.message; confirmLabel = $event.detail.confirm ?? @js(__('Confirm')); danger = $event.detail.danger ?? false; run = $event.detail.run; $dispatch('ui-sheet', { name: 'confirm-action' })">
    <x-ui.sheet name="confirm-action" :title="__('Are you sure?')">
        <p class="text-ui-muted" x-text="message"></p>
        <div class="grid grid-cols-2 gap-2">
            <x-ui.button variant="ghost" @click="$dispatch('ui-sheet', { name: 'confirm-action', open: false })">{{ __('Cancel') }}</x-ui.button>
            <button type="button" @click="run && run(); $dispatch('ui-sheet', { name: 'confirm-action', open: false })"
                class="inline-flex w-full items-center justify-center rounded-2xl px-4 py-3.5 font-display text-lg font-extrabold uppercase tracking-wide transition active:scale-[.97]"
                :class="danger ? 'bg-bad text-white' : 'bg-club text-club-ink'"><span x-text="confirmLabel"></span></button>
        </div>
    </x-ui.sheet>
</div>
