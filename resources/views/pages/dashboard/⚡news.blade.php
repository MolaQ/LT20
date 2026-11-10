<?php

use App\Enums\Permission;
use App\Models\News;
use App\Support\Audit;
use App\Support\NewsHtml;
use App\Support\NewsImage;
use App\Support\SystemFeed;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Newsy na stronę główną: lista z wyszukiwarką, filtrem i sortowaniem, dodawanie, edycja, publikacja
 * i usuwanie (uprawnienie news-create). Niepublikowany news to szkic. Pierwsza publikacja trafia też do
 * informacji systemowych. Treść pisze się w edytorze (x-news-editor), zapisuje jako HTML po białej liście (NewsHtml).
 */
new class extends Component {
    use WithFileUploads;
    use WithPagination;

    /** Sortowanie listy: klucz => etykieta. */
    public const SORTS = ['newest', 'oldest', 'title', 'popular', 'unpopular'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public ?int $editId = null;
    public string $title = '';
    public string $body = '';
    public bool $publish = true;

    /** Obrazek wgrywany z edytora (od razu zapisywany przez storeImage). */
    public $image = null;

    public ?int $deleteId = null;

    public function render(): View
    {
        return $this->view()->title(__('News'));
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'statusFilter', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'statusFilter', 'sort');
        $this->resetPage();
    }

    #[Computed]
    public function counts(): array
    {
        return [
            '' => News::count(),
            'published' => News::whereNotNull('published_at')->count(),
            'draft' => News::whereNull('published_at')->count(),
        ];
    }

    #[Computed]
    public function items()
    {
        $search = trim($this->search);

        $query = News::with('author')
            ->withVoteCounts()
            ->when($this->statusFilter === 'published', fn ($q) => $q->whereNotNull('published_at'))
            ->when($this->statusFilter === 'draft', fn ($q) => $q->whereNull('published_at'))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like));
            });

        match (in_array($this->sort, self::SORTS, true) ? $this->sort : 'newest') {
            'oldest' => $query->orderByRaw('coalesce(published_at, created_at) asc')->oldest('id'),
            'title' => $query->orderBy('title'),
            'popular' => $query->orderByDesc('up_count')->orderBy('down_count'),
            'unpopular' => $query->orderByDesc('down_count')->orderBy('up_count'),
            // Najnowsze: najpierw szkice, potem według daty publikacji.
            default => $query->orderByRaw('published_at is not null')->latest('published_at')->latest('id'),
        };

        return $query->paginate(20);
    }

    public function openForm(?int $id = null): void
    {
        $this->authorize(Permission::NewsCreate->value);
        $this->reset('editId', 'title', 'body', 'publish', 'image');
        $this->resetValidation();

        if ($id) {
            $news = News::findOrFail($id);
            $this->editId = $news->id;
            $this->title = $news->title;
            // Stary news (zwykły tekst) otwiera się w edytorze jako akapity.
            $this->body = NewsHtml::render($news->body);
            $this->publish = $news->published_at !== null;
        }

        $this->dispatch('news-editor-load', html: $this->body);
        $this->dispatch('ui-sheet', name: 'news-form');
    }

    /** Zapisuje obrazek wgrany w edytorze i zwraca jego adres (edytor wstawia go w miejscu kursora). */
    public function storeImage(): ?string
    {
        $this->authorize(Permission::NewsCreate->value);
        $this->validate(['image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:5120']], [], ['image' => __('Image')]);

        $url = NewsImage::store($this->image);
        $this->reset('image');

        return $url;
    }

    public function save(): void
    {
        $this->authorize(Permission::NewsCreate->value);

        $this->body = NewsHtml::clean($this->body);

        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:30000'],
        ], [], ['title' => __('Title'), 'body' => __('Content')]);

        if (NewsHtml::plain($this->body) === '' && ! str_contains($this->body, '<img')) {
            $this->addError('body', __('The news has no content.'));

            return;
        }

        $news = $this->editId ? News::findOrFail($this->editId) : new News(['user_id' => auth()->id()]);
        $old = $news->only(['title', 'published_at']);
        $firstPublish = $this->publish && $news->published_at === null;

        $news->fill([
            'title' => trim($this->title),
            'body' => $this->body,
            // Data publikacji zostaje przy edycji opublikowanego newsa.
            'published_at' => $this->publish ? ($news->published_at ?? now()) : null,
        ])->save();

        Audit::log($this->editId ? 'news.updated' : 'news.created', null, $old, $news->only(['title', 'published_at']), $news->title);

        if ($firstPublish) {
            SystemFeed::record('news', 'News: :title', ['title' => $news->title], 'news.show', ['news' => $news->id], auth()->id());
        }

        $this->dispatch('ui-sheet', name: 'news-form', open: false);
        $this->reset('editId', 'title', 'body', 'publish');
        unset($this->items, $this->counts);
        $this->dispatch('ui-toast', variant: 'success', text: __('News saved.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize(Permission::NewsCreate->value);
        $this->deleteId = News::findOrFail($id)->id;

        $this->dispatch('ui-sheet', name: 'delete-news');
    }

    public function delete(): void
    {
        $this->authorize(Permission::NewsCreate->value);
        $news = News::findOrFail((int) $this->deleteId);
        $news->delete(); // oceny usuwa klucz obcy (cascade)

        Audit::log('news.deleted', null, ['title' => $news->title], [], $news->title);

        $this->dispatch('ui-sheet', name: 'delete-news', open: false);
        $this->reset('deleteId');
        unset($this->items, $this->counts);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('News') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Announcements on the home page. Logged-in players can rate them.') }}</x-ui.subheading>
        </div>
        <x-ui.btn variant="primary" icon="plus" wire:click="openForm">{{ __('New news') }}</x-ui.btn>
    </div>

    {{-- Filtry jako przyciski: status (z licznikami) i sortowanie; nad nimi wyszukiwarka. --}}
    <div class="space-y-2">
        <div class="w-full sm:w-80">
            <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search in title and content...')" />
        </div>
        <x-ui.filter-pills model="statusFilter" :value="$statusFilter" :label="__('Status')" :counts="$this->counts"
            :options="['' => __('All'), 'published' => __('Published'), 'draft' => __('Drafts')]" />
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.filter-pills model="sort" :value="$sort" :label="__('Sort')" :options="[
                'newest' => __('Newest first'),
                'oldest' => __('Oldest first'),
                'title' => __('Title A–Z'),
                'popular' => __('Most thumbs up'),
                'unpopular' => __('Most thumbs down'),
            ]" />
            @if ($search !== '' || $statusFilter !== '' || $sort !== 'newest')
                <x-ui.btn size="sm" variant="ghost" icon="x-mark" wire:click="resetFilters">{{ __('Clear filters') }}</x-ui.btn>
            @endif
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($this->items as $news)
            <x-ui.card wire:key="news-{{ $news->id }}" class="space-y-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.heading>{{ $news->title }}</x-ui.heading>
                            @if ($news->published_at)
                                <x-ui.badge size="sm" color="green">{{ __('Published') }}</x-ui.badge>
                            @else
                                <x-ui.badge size="sm" color="zinc">{{ __('Draft') }}</x-ui.badge>
                            @endif
                        </div>
                        <x-ui.text size="sm">
                            {{ $news->author?->name ?? '–' }}
                            · {{ ($news->published_at ?? $news->created_at)?->format('d.m.Y H:i') }}
                            · <span class="text-green-600">👍 {{ $news->up_count }}</span>
                            · <span class="text-red-600">👎 {{ $news->down_count }}</span>
                        </x-ui.text>
                    </div>
                    <div class="flex gap-1">
                        <x-ui.btn size="xs" variant="ghost" icon="pencil" wire:click="openForm({{ $news->id }})" :aria-label="__('Edit')" />
                        <x-ui.btn size="xs" variant="ghost" icon="trash" wire:click="confirmDelete({{ $news->id }})" :aria-label="__('Delete')" />
                    </div>
                </div>
                <x-ui.text class="line-clamp-3 whitespace-pre-line">{{ \App\Support\NewsHtml::plain($news->body) }}</x-ui.text>
            </x-ui.card>
        @empty
            <x-ui.card>
                <x-ui.text>{{ $search !== '' || $statusFilter !== '' ? __('No news match the filters.') : __('No news yet.') }}</x-ui.text>
            </x-ui.card>
        @endforelse

        {{ $this->items->links() }}
    </div>

    <x-ui.modal name="news-form" class="w-full max-w-3xl">
        <form wire:submit="save" class="space-y-5">
            <x-ui.heading size="lg">{{ $editId ? __('Edit news') : __('New news') }}</x-ui.heading>
            <x-ui.input wire:model="title" :label="__('Title')" maxlength="150" required />
            <div>
                <x-news-editor :label="__('Content')" />
                <x-ui.error name="body" class="mt-1" />
                <x-ui.error name="image" class="mt-1" />
            </div>
            <x-ui.switch wire:model="publish" :label="__('Publish on the home page')" />
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn type="submit" variant="primary">{{ __('Save') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="delete-news" class="w-full max-w-md">
        <div class="space-y-5">
            <x-ui.heading size="lg">{{ __('Delete this news?') }}</x-ui.heading>
            <x-ui.text>{{ __('Its ratings will be deleted too.') }}</x-ui.text>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="delete">{{ __('Delete') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
</div>
