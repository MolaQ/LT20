{{--
    Edytor treści newsa (resources/js/news-editor.js). Wymaga w komponencie Livewire:
    własności body (HTML) i image (plik z WithFileUploads) oraz metody storeImage(): ?string (adres obrazka).
    Treść wczytuje zdarzenie przeglądarki news-editor-load z polem html (przy otwarciu formularza).
--}}
@props(['label' => null])

@php
    $socials = \App\Support\NewsHtml::SOCIALS;
    $btn = 'inline-flex size-8 items-center justify-center rounded-lg text-ui-muted transition hover:bg-ui-raised hover:text-ui-ink';
@endphp

<div x-data="newsEditor()" x-on:news-editor-load.window="load($event.detail.html)" wire:ignore class="flex flex-col gap-1.5">
    @if ($label)
        <span class="text-sm font-semibold">{{ $label }}</span>
    @endif

    <div class="overflow-hidden rounded-xl border border-ui-line bg-ui-surface focus-within:border-club focus-within:ring-2 focus-within:ring-club/30">
        {{-- Pasek narzędzi: mousedown.prevent zostawia zaznaczenie w treści. --}}
        <div class="flex flex-wrap items-center gap-0.5 border-b border-ui-line bg-ui-raised/50 p-1" role="toolbar" aria-label="{{ __('Formatting') }}">
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="cmd('bold')" title="{{ __('Bold') }}" aria-label="{{ __('Bold') }}"><x-ui.icon name="bold" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="cmd('italic')" title="{{ __('Italic') }}" aria-label="{{ __('Italic') }}"><x-ui.icon name="italic" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="cmd('underline')" title="{{ __('Underline') }}" aria-label="{{ __('Underline') }}"><x-ui.icon name="underline" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="cmd('strikeThrough')" title="{{ __('Strikethrough') }}" aria-label="{{ __('Strikethrough') }}"><x-ui.icon name="strikethrough" variant="mini" /></button>
            <span class="mx-1 h-5 w-px bg-ui-line" aria-hidden="true"></span>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="block('h2')" title="{{ __('Heading') }}" aria-label="{{ __('Heading') }}"><x-ui.icon name="h2" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="block('h3')" title="{{ __('Subheading') }}" aria-label="{{ __('Subheading') }}"><x-ui.icon name="h3" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="cmd('insertUnorderedList')" title="{{ __('Bulleted list') }}" aria-label="{{ __('Bulleted list') }}"><x-ui.icon name="list-bullet" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="cmd('insertOrderedList')" title="{{ __('Numbered list') }}" aria-label="{{ __('Numbered list') }}"><x-ui.icon name="numbered-list" variant="mini" /></button>
            <button type="button" class="{{ $btn }} font-serif text-lg leading-none" @mousedown.prevent @click="block('blockquote')" title="{{ __('Quote') }}" aria-label="{{ __('Quote') }}">&ldquo;</button>
            <span class="mx-1 h-5 w-px bg-ui-line" aria-hidden="true"></span>
            <button type="button" class="{{ $btn }}" :class="panel === 'link' && 'bg-ui-raised text-ui-ink'" @mousedown.prevent @click="openPanel('link')" title="{{ __('Link') }}" aria-label="{{ __('Link') }}"><x-ui.icon name="link" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="cmd('unlink')" title="{{ __('Remove link') }}" aria-label="{{ __('Remove link') }}"><x-ui.icon name="link-slash" variant="mini" /></button>
            <button type="button" class="{{ $btn }} w-auto gap-1 px-2 text-xs font-semibold" :class="panel === 'social' && 'bg-ui-raised text-ui-ink'" @mousedown.prevent @click="openPanel('social')" title="{{ __('Social media icon') }}">
                <span class="social-facebook inline-block size-4" style="background-color: var(--social-color); mask: var(--social-icon) center / contain no-repeat" aria-hidden="true"></span>{{ __('Social') }}
            </button>
            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="pickImage()" :disabled="uploading" title="{{ __('Image') }}" aria-label="{{ __('Image') }}"><x-ui.icon name="photo" variant="mini" /></button>
            <span x-show="uploading" x-cloak class="px-1 text-xs tabular-nums text-ui-muted" x-text="progress + '%'"></span>
            <input type="file" x-ref="file" class="hidden" accept="image/png,image/jpeg,image/webp,image/gif" @change="uploadImage($event)">
        </div>

        {{-- Wstawianie linku. --}}
        <div x-show="panel === 'link'" x-cloak class="flex flex-wrap items-center gap-2 border-b border-ui-line p-2">
            <input type="url" x-model="linkUrl" @keydown.enter.prevent="addLink()" placeholder="https://..."
                class="h-9 min-w-0 flex-1 rounded-lg border border-ui-line bg-ui-surface px-3 text-sm focus:border-club focus:outline-none">
            <x-ui.btn size="sm" variant="primary" type="button" @click="addLink()">{{ __('Insert') }}</x-ui.btn>
        </div>

        {{-- Wstawianie ikony social mediów z linkiem. --}}
        <div x-show="panel === 'social'" x-cloak class="space-y-2 border-b border-ui-line p-2">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($socials as $key => $name)
                    <button type="button" @click="social = '{{ $key }}'"
                        class="social-link social-{{ $key }} cursor-pointer text-xs"
                        :class="social === '{{ $key }}' ? 'ring-2 ring-club' : 'opacity-70 hover:opacity-100'">{{ $name }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input type="url" x-model="socialUrl" @keydown.enter.prevent="addSocial(@js($socials))" placeholder="{{ __('Profile or post address') }}"
                    class="h-9 min-w-0 flex-1 rounded-lg border border-ui-line bg-ui-surface px-3 text-sm focus:border-club focus:outline-none">
                <input type="text" x-model="socialLabel" maxlength="60" :placeholder="@js($socials)[social]"
                    class="h-9 w-36 rounded-lg border border-ui-line bg-ui-surface px-3 text-sm focus:border-club focus:outline-none" aria-label="{{ __('Label') }}">
                <x-ui.btn size="sm" variant="primary" type="button" @click="addSocial(@js($socials))">{{ __('Insert') }}</x-ui.btn>
            </div>
        </div>

        {{-- Zaznaczony obrazek: szerokość, wyrównanie, opis i usunięcie. --}}
        <div x-show="image" x-cloak class="flex flex-wrap items-center gap-2 border-b border-ui-line bg-ui-raised/50 p-2 text-xs">
            <span class="font-semibold">{{ __('Image width') }}</span>
            <template x-for="w in widths" :key="w">
                <button type="button" @click="setWidth(w)" class="rounded-md border px-2 py-1 tabular-nums"
                    :class="image && image.dataset.width == w ? 'border-club bg-club text-white' : 'border-ui-line hover:bg-ui-surface'" x-text="w + '%'"></button>
            </template>
            <span class="mx-1 h-5 w-px bg-ui-line" aria-hidden="true"></span>
            <button type="button" class="{{ $btn }}" @click="setAlign('left')" title="{{ __('Left') }}" aria-label="{{ __('Left') }}"><x-ui.icon name="bars-3-bottom-left" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @click="setAlign('center')" title="{{ __('Center') }}" aria-label="{{ __('Center') }}"><x-ui.icon name="bars-3" variant="mini" /></button>
            <button type="button" class="{{ $btn }}" @click="setAlign('right')" title="{{ __('Right') }}" aria-label="{{ __('Right') }}"><x-ui.icon name="bars-3-bottom-right" variant="mini" /></button>
            <input type="text" x-model="imageAlt" @input.debounce.300ms="setAlt()" maxlength="150" placeholder="{{ __('Image description') }}"
                class="h-8 min-w-0 flex-1 rounded-lg border border-ui-line bg-ui-surface px-2 text-xs focus:border-club focus:outline-none">
            <button type="button" class="{{ $btn }} text-bad" @click="removeImage()" title="{{ __('Remove image') }}" aria-label="{{ __('Remove image') }}"><x-ui.icon name="trash" variant="mini" /></button>
        </div>

        <div class="relative">
            <div x-ref="area" contenteditable="true" role="textbox" aria-multiline="true" aria-label="{{ $label ?? __('Content') }}"
                @input="sync()" @keyup="saveRange()" @mouseup="saveRange()" @click="clickArea($event)" @paste.prevent="paste($event)"
                class="news-content min-h-56 max-h-[60vh] overflow-y-auto px-4 py-3 text-sm text-ui-ink focus:outline-none [&_img]:cursor-pointer"></div>
            <span x-show="empty" class="pointer-events-none absolute left-4 top-3 text-sm text-ui-muted">{{ __('Write the news...') }}</span>
        </div>
    </div>
</div>
