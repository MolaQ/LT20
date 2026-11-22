{{--
    Niebieskie „i” pod tabelą: otwiera okno x-table-criteria (kolejność w tabeli). Ukryte na udostępnianej grafice.
--}}
<button type="button" data-share-hide x-data @click="$dispatch('ui-sheet', { name: 'table-criteria' })"
    {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full text-xs font-medium opacity-95 hover:opacity-100') }}
    title="{{ __('How the table is ordered') }}">
    <span class="grid size-5 place-items-center rounded-full bg-sky-500 text-white shadow ring-2 ring-white/70">
        <x-ui.icon name="information-circle" variant="micro" class="size-3.5" />
    </span>
    <span class="underline decoration-dotted underline-offset-2">{{ __('Table order') }}</span>
</button>
