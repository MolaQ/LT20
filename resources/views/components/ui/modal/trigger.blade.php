{{-- Otwiera okno o danej nazwie (zamiennik flux:modal.trigger). --}}
@props(['name'])

<span class="contents" @click="$dispatch('ui-sheet', { name: @js($name) })">{{ $slot }}</span>
