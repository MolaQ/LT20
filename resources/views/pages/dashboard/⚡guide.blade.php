<?php

use App\Enums\Permission;
use App\Models\GuideItem;
use App\Support\Audit;
use App\Support\Guide;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Zasady gry i FAQ (14.11.2026): admin poprawia, dodaje, usuwa i przestawia pozycje akordeonów ze stron /rules i /faq.
 * Przy pierwszym wejściu teksty domyślne kopiują się do tabeli guide_items (Guide::ensureEditable), a „Przywróć domyślne”
 * wraca do nich. Treść jak w newsach: pusty wiersz = nowy akapit, można użyć prostego HTML (pogrubienie, link, lista).
 * Uprawnienie: news-create (ta sama grupa „Treści”).
 */
new class extends Component {
    #[Url(as: 'rodzaj', except: 'rules')]
    public string $kind = 'rules';

    public ?int $editId = null;
    public string $title = '';
    public string $body = '';

    public function mount(): void
    {
        $this->authorizeEdit();
        $this->kind = in_array($this->kind, GuideItem::KINDS, true) ? $this->kind : 'rules';
        Guide::ensureEditable($this->kind);
    }

    public function render(): View
    {
        return $this->view()->title(__('Rules and FAQ'));
    }

    public function updatedKind(): void
    {
        $this->kind = in_array($this->kind, GuideItem::KINDS, true) ? $this->kind : 'rules';
        Guide::ensureEditable($this->kind);
        $this->cancel();
        unset($this->items);
    }

    #[Computed]
    public function items()
    {
        return GuideItem::where('kind', $this->kind)->orderBy('position')->orderBy('id')->get();
    }

    public function edit(int $id): void
    {
        $item = $this->find($id);
        $this->editId = $item->id;
        $this->title = $item->title;
        $this->body = $item->body;
    }

    /** Nowa pozycja na końcu listy (od razu w trybie edycji). */
    public function add(): void
    {
        $this->authorizeEdit();
        $this->editId = 0;
        $this->title = '';
        $this->body = '';
    }

    public function save(): void
    {
        $this->authorizeEdit();
        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
        ], [], ['title' => __('Title'), 'body' => __('Content')]);

        if ($this->editId) {
            $this->find($this->editId)->update($data);
        } else {
            GuideItem::create($data + ['kind' => $this->kind, 'position' => (int) GuideItem::where('kind', $this->kind)->max('position') + 1]);
        }

        Audit::log('guide.saved', null, [], ['kind' => $this->kind], $data['title']);
        $this->cancel();
        unset($this->items);
        $this->dispatch('ui-toast', text: __('Saved.'), variant: 'success');
    }

    public function cancel(): void
    {
        $this->reset('editId', 'title', 'body');
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        $this->authorizeEdit();
        $item = $this->find($id);
        Audit::log('guide.deleted', null, ['kind' => $this->kind], [], $item->title);
        $item->delete();
        unset($this->items);
        $this->dispatch('ui-toast', text: __('Deleted.'), variant: 'success');
    }

    /** Przestawia pozycję o jedno miejsce w górę (-1) albo w dół (1). */
    public function move(int $id, int $direction): void
    {
        $this->authorizeEdit();
        $list = $this->items->values();
        $index = $list->search(fn (GuideItem $i) => $i->id === $id);
        $other = $index === false ? null : $list->get($index + ($direction < 0 ? -1 : 1));

        if (!$other) {
            return;
        }

        // Kolejność od nowa (1, 2, 3 …), żeby równe pozycje nie blokowały przestawiania.
        $ids = $list->pluck('id')->all();
        [$ids[$index], $ids[$index + ($direction < 0 ? -1 : 1)]] = [$other->id, $id];
        foreach ($ids as $i => $itemId) {
            GuideItem::whereKey($itemId)->update(['position' => $i + 1]);
        }

        unset($this->items);
    }

    /** Wraca do tekstów domyślnych (zmiany z panelu znikają). */
    public function restoreDefaults(): void
    {
        $this->authorizeEdit();
        GuideItem::where('kind', $this->kind)->delete();
        Guide::ensureEditable($this->kind);
        Audit::log('guide.restored', null, [], ['kind' => $this->kind], $this->kind === 'faq' ? __('FAQ') : __('Rules of the game'));
        $this->dispatch('ui-sheet', name: 'restore-guide', open: false);
        $this->cancel();
        unset($this->items);
        $this->dispatch('ui-toast', text: __('Default texts restored.'), variant: 'success');
    }

    private function find(int $id): GuideItem
    {
        return GuideItem::where('kind', $this->kind)->findOrFail($id);
    }

    private function authorizeEdit(): void
    {
        abort_unless(auth()->user()?->can(Permission::NewsCreate->value), 403);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Rules and FAQ') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Texts of the Rules of the game and FAQ pages. An empty line starts a new paragraph; simple HTML (bold, links, lists) works too.') }}</x-ui.subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.btn variant="ghost" icon="arrow-top-right-on-square" :href="$kind === 'faq' ? route('faq') : route('rules')" target="_blank">{{ __('Open the page') }}</x-ui.btn>
            <x-ui.btn variant="primary" icon="plus" wire:click="add">{{ __('Add an item') }}</x-ui.btn>
        </div>
    </div>

    <x-ui.segmented wire:model.live="kind" class="max-w-sm" :options="['rules' => __('Rules of the game'), 'faq' => __('FAQ')]" />

    <div class="space-y-2">
        @foreach ($this->items as $item)
            <div wire:key="guide-{{ $item->id }}" class="rounded-2xl border border-ui-line bg-ui-surface shadow-sm">
                @if ($editId === $item->id)
                    @include('partials.guide-edit-form')
                @else
                    <div class="flex items-start gap-3 p-4">
                        <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-ui-raised text-xs font-bold tabular-nums">{{ $loop->iteration }}</span>
                        <button type="button" wire:click="edit({{ $item->id }})" class="min-w-0 flex-1 text-start">
                            <span class="block font-semibold">{{ $item->title }}</span>
                            <span class="mt-0.5 line-clamp-2 block text-sm text-ui-muted">{{ \Illuminate\Support\Str::limit(strip_tags($item->body), 220) }}</span>
                        </button>
                        <div class="flex shrink-0 items-center gap-0.5">
                            <x-ui.btn variant="ghost" size="sm" icon="chevron-up" :disabled="$loop->first" wire:click="move({{ $item->id }}, -1)" :aria-label="__('Move up')" />
                            <x-ui.btn variant="ghost" size="sm" icon="chevron-down" :disabled="$loop->last" wire:click="move({{ $item->id }}, 1)" :aria-label="__('Move down')" />
                            <x-ui.btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $item->id }})" :aria-label="__('Edit')" />
                            <x-ui.btn variant="ghost" size="sm" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Delete this item?') }}" :aria-label="__('Delete')" />
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        @if ($editId === 0)
            <div class="rounded-2xl border-2 border-dashed border-club/50 bg-ui-surface shadow-sm">
                @include('partials.guide-edit-form')
            </div>
        @endif
    </div>

    <div>
        <x-ui.btn variant="ghost" size="sm" icon="arrow-uturn-left" x-on:click="$dispatch('ui-sheet', { name: 'restore-guide' })">{{ __('Restore default texts') }}</x-ui.btn>
    </div>

    <x-ui.modal name="restore-guide" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ __('Restore default texts?') }}</x-ui.heading>
                <x-ui.text class="mt-2">{{ __('Your changes on this tab will be replaced with the default texts.') }}</x-ui.text>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="restoreDefaults">{{ __('Restore') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
</div>
