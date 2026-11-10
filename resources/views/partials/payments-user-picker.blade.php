{{-- Wybór gracza w oknach strony Wpłaty (dashboard/payments): wyszukiwarka i wybrany gracz. --}}
<div class="space-y-2">
    @if ($this->selectedUser)
        <div class="flex items-center gap-2 rounded-lg border border-ui-line p-2 text-sm">
            <x-ui.icon name="user" class="size-4 text-zinc-400" />
            <span class="min-w-0 flex-1 truncate">{{ $this->selectedUser->name }} ({{ $this->selectedUser->email }})</span>
            <x-ui.btn size="xs" variant="ghost" icon="x-mark" wire:click="$set('userId', null)" :aria-label="__('Change')" />
        </div>
    @else
        <x-ui.input wire:model.live.debounce.300ms="userSearch" icon="magnifying-glass" :label="__('Player')"
            :placeholder="__('Name, e-mail or team...')" />
        @foreach ($this->userResults as $result)
            <button type="button" wire:click="pickUser({{ $result->id }})" wire:key="pick-{{ $result->id }}"
                class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-ui-raised">
                <span class="min-w-0 flex-1 truncate">{{ $result->name }}</span>
                <span class="text-xs text-ui-muted">{{ $result->email }}</span>
            </button>
        @endforeach
    @endif
    <x-ui.error name="userId" />
</div>
