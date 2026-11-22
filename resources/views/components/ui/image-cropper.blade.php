{{--
    Edytor obrazka przed wysłaniem (logo i banery sponsorów), przeniesiony z jokerpila.pl (x-photo-cropper):
    wybór pliku, kadr z podglądem, powiększanie i obrót, kształt kadru (dowolny, kwadrat, koło),
    usuwanie tła od brzegów, usuwanie i zamiana koloru pipetą, podgląd na ciemnym tle.
    Przycięty plik trafia do właściwości Livewire z „model” (dalej działa zwykła walidacja pliku).
    Użycie: <x-ui.image-cropper model="logo" aspect="1/1" format="png" :width="600" :current="$url" />
--}}
@props([
    'model',
    // Obecny obrazek (adres) do miniatury obok przycisku.
    'current' => null,
    // Proporcje kadru, np. '1/1', '24/5' albo 'free' (dowolny kadr, np. logo).
    'aspect' => '1/1',
    // Szerokość wysyłanego pliku w pikselach (przy dowolnym kadrze: najdłuższy bok).
    'width' => 800,
    // png dla logo z przezroczystym tłem (narzędzia tła i kolorów), jpeg dla zdjęć i banerów.
    'format' => 'png',
    'label' => null,
    'hint' => null,
])
@php
    [$ratioW, $ratioH] = $aspect === 'free' ? [0, 0] : array_map('floatval', explode('/', $aspect));
    $ratio = $ratioH > 0 ? $ratioW / $ratioH : 0;
    $boxStyle = 'aspect-ratio: '.($ratio > 0 ? $ratioW.' / '.$ratioH : '1 / 1');
    $boxWidth = $ratio >= 1.5 ? 'w-full max-w-sm' : 'w-20';
    $messages = [
        'type' => __('Choose an image file (JPG, PNG, WebP).'),
        'load' => __('Could not load the current image.'),
        'upload' => __('Could not upload the image. Please try again.'),
        'timeout' => __('The server did not respond. Refresh the page and try again.'),
    ];
    $btn = 'rounded-lg bg-ui-surface px-3 py-1.5 text-sm font-medium text-ui-ink ring-1 ring-ui-line transition hover:bg-ui-raised disabled:opacity-50';
@endphp
<div x-data="imageCropper(@js($model), @js(['aspect' => $ratio, 'width' => (int) $width, 'format' => $format, 'messages' => $messages]))"
    {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div @class(['flex gap-4', 'flex-col' => $ratio >= 1.5, 'items-start' => $ratio < 1.5])>
        {{-- Miniatura: podgląd kadru na żywo albo obecny obrazek --}}
        <div class="{{ $boxWidth }} shrink-0 overflow-hidden rounded-lg border border-ui-line bg-ui-raised" style="{{ $boxStyle }}">
            <div x-show="source" x-ref="preview" class="size-full overflow-hidden"></div>
            <div x-show="! source" class="size-full">
                @if ($current)
                    <img src="{{ $current }}" alt="" class="size-full {{ $aspect === 'free' ? 'object-contain p-1' : 'object-cover' }}">
                @else
                    <div class="flex size-full items-center justify-center text-ui-muted">
                        <x-ui.icon name="photo" class="size-7" />
                    </div>
                @endif
            </div>
        </div>

        <div class="min-w-0 flex-1 space-y-2 text-sm">
            <label class="inline-flex cursor-pointer items-center gap-2 {{ $btn }} font-semibold">
                <x-ui.icon name="arrow-up-tray" variant="mini" class="size-4" />
                {{ $label ?? __('Choose image') }}
                <input type="file" accept="image/*" class="sr-only" x-ref="input" @change="choose($event)">
            </label>
            @if ($hint)
                <p class="text-xs text-ui-muted">{{ $hint }}</p>
            @endif
            <p x-show="error" x-text="error" class="text-xs text-bad"></p>
            <p wire:loading wire:target="{{ $model }}" class="text-xs text-ui-muted">{{ __('Uploading...') }}</p>
        </div>
    </div>

    {{-- Edytor: pole kadrowania i narzędzia --}}
    {{-- data-sheet-nodrag: przeciąganie kadru na telefonie nie zamyka okna (arkusze i okna zamykane przeciągnięciem) --}}
    <div x-show="source" x-cloak class="space-y-2" data-sheet-nodrag>
        <div wire:ignore :class="[shape === 'circle' && 'cropper-round', darkPreview && '!bg-slate-900 !bg-none']" :style="shape === 'circle' && circleFill ? `--circle-fill: ${circleFill}` : ''"
            class="h-[40vh] min-h-56 overflow-hidden rounded-xl {{ $format === 'png' ? 'bg-[repeating-conic-gradient(#e4e4e7_0_25%,#fff_0_50%)] bg-[length:16px_16px]' : 'bg-zinc-900' }}">
            <img x-ref="image" :src="source" alt="{{ __('Cropping') }}" class="block max-w-full">
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <button type="button" @click="zoom(0.1)" class="{{ $btn }}" aria-label="{{ __('Zoom in') }}" title="{{ __('Zoom in') }}">＋</button>
            <button type="button" @click="zoom(-0.1)" class="{{ $btn }}" aria-label="{{ __('Zoom out') }}" title="{{ __('Zoom out') }}">－</button>
            <button type="button" @click="rotate(90)" class="{{ $btn }}">{{ __('Rotate') }} ↻</button>
            @if ($format === 'png' && $aspect !== 'free')
                {{-- Cały obrazek w kadrze (np. wysokie trofeum w kwadracie), z przezroczystym marginesem --}}
                <button type="button" @click="fitWhole()" class="{{ $btn }}">{{ __('Fit whole image') }}</button>
            @endif

            @if ($format === 'png' && $aspect === 'free')
                <span class="inline-flex overflow-hidden rounded-lg ring-1 ring-ui-line" role="group" aria-label="{{ __('Crop shape') }}">
                    @foreach (['free' => __('Free crop'), 'square' => __('Square 1:1'), 'circle' => __('Circle')] as $shapeKey => $shapeLabel)
                        <button type="button" @click="setShape(@js($shapeKey))" :aria-pressed="shape === @js($shapeKey)"
                            :class="shape === @js($shapeKey) ? 'bg-club text-club-ink' : 'bg-ui-surface text-ui-ink hover:bg-ui-raised'"
                            class="px-3 py-1.5 not-first:border-l not-first:border-ui-line">{{ $shapeLabel }}</button>
                    @endforeach
                </span>
            @endif

            <button type="button" @click="destroy()" class="{{ $btn }}">{{ __('Cancel') }}</button>
        </div>

        @if ($format === 'png' && $aspect === 'free')
            {{-- Koło: całe logo w kole i kolor tła koła (domyślnie kolor rogu logo), reszta poza kołem przezroczysta --}}
            <div x-show="shape === 'circle'" x-cloak class="flex flex-wrap items-center gap-2 rounded-lg bg-ui-raised/60 p-2 text-sm ring-1 ring-ui-line">
                <button type="button" @click="fitCircle()" class="{{ $btn }} font-semibold"
                    title="{{ __('Shrinks the logo so that all of it fits in the circle.') }}">{{ __('Whole logo in the circle') }}</button>
                <span class="text-xs text-ui-muted">{{ __('Circle background') }}</span>
                <span class="inline-flex overflow-hidden rounded-lg ring-1 ring-ui-line text-xs">
                    <button type="button" @click="circleFill = null" :class="circleFill === null ? 'bg-club text-club-ink' : 'bg-ui-surface'"
                        class="px-2.5 py-1.5 font-semibold">{{ __('Transparent') }}</button>
                    <label class="flex cursor-pointer items-center gap-1.5 border-l border-ui-line px-2.5 py-1 font-semibold"
                        :class="circleFill !== null ? 'bg-club text-club-ink' : 'bg-ui-surface'">{{ __('Colour') }}
                        <input type="color" :value="circleFill || cornerColor() || '#ffffff'" @input="circleFill = $event.target.value"
                            class="h-5 w-7 cursor-pointer rounded border-0 bg-transparent p-0" aria-label="{{ __('Circle background') }}">
                    </label>
                </span>
            </div>
        @endif

        @if ($format === 'png')
            {{-- Tło i kolory (logo z przezroczystością) --}}
            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-ui-raised/60 p-2 text-sm ring-1 ring-ui-line">
                <button type="button" @click="removeBackground()" :disabled="! ready" class="{{ $btn }} font-semibold"
                    title="{{ __('Removes a solid background (e.g. white) from the edges; text inside the logo stays.') }}">{{ __('Remove background') }}</button>
                <button type="button" @click="startPicking()" :disabled="! ready" :aria-pressed="picking === 'color'"
                    :class="picking === 'color' && '!bg-club !text-club-ink'" class="{{ $btn }} font-semibold"
                    title="{{ __('Then click the logo: the chosen colour disappears in the whole image, also inside letters.') }}">{{ __('Remove colour') }}</button>
                <span class="inline-flex items-center rounded-lg ring-1 ring-ui-line" :class="picking === 'replace' ? 'bg-club text-club-ink' : 'bg-ui-surface'">
                    <button type="button" @click="startPicking('replace')" :disabled="! ready" :aria-pressed="picking === 'replace'"
                        class="px-3 py-1.5 font-semibold disabled:opacity-50"
                        title="{{ __('Then click the logo: the chosen colour changes to the colour next to it.') }}">{{ __('Replace colour') }}</button>
                    <label class="flex items-center gap-1 border-l border-ui-line py-1 pe-2 ps-1.5 text-xs">{{ __('to') }}
                        <input type="color" :value="replaceTo" @change="setReplaceTo($event.target.value)"
                            class="h-5 w-7 cursor-pointer rounded border-0 bg-transparent p-0" aria-label="{{ __('New colour') }}">
                    </label>
                </span>
                <template x-for="item in colorOps()" :key="item.index">
                    <button type="button" @click="removeColorOp(item.index)"
                        class="inline-flex items-center gap-1 rounded-full bg-ui-surface py-0.5 pe-2 ps-0.5 text-xs text-ui-muted ring-1 ring-ui-line hover:bg-ui-raised"
                        title="{{ __('Undo') }}">
                        <span class="size-4 rounded-full ring-1 ring-ui-line" :style="`background: ${rgb(item.op.color || item.op.replace)}`"></span>
                        <template x-if="item.op.replace"><span class="inline-flex items-center gap-1">→<span class="size-4 rounded-full ring-1 ring-ui-line" :style="`background: ${rgb(item.op.to)}`"></span></span></template>
                        ✕
                    </button>
                </template>
                <label class="flex items-center gap-1.5 text-xs text-ui-muted">{{ __('sensitivity') }}
                    <input type="range" min="5" max="120" step="5" x-model="tolerance" @change="ops.length && applyOps()" class="w-24 accent-[var(--club-primary)]">
                </label>
                <button type="button" x-show="backgroundRemoved" @click="restoreBackground()" class="text-xs font-semibold text-ui-muted underline hover:text-ui-ink">{{ __('restore') }}</button>
                <label class="flex items-center gap-1.5 text-xs text-ui-muted">
                    <input type="checkbox" x-model="darkPreview" class="rounded border-ui-line">{{ __('dark preview background') }}
                </label>
                <p x-show="picking === 'color'" x-cloak class="w-full text-xs font-semibold text-club">{{ __('Click the colour in the logo that should disappear (e.g. a white fill).') }}</p>
                <p x-show="picking === 'replace'" x-cloak class="w-full text-xs font-semibold text-club">{{ __('Click the colour in the logo that should change. Pick the new colour in the “to” field.') }}</p>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="button" @click="confirm()" :disabled="! ready || uploading"
                class="rounded-lg bg-club px-4 py-1.5 text-sm font-semibold text-club-ink transition hover:opacity-90 disabled:opacity-50">
                <span x-show="! uploading">{{ __('Use this crop') }}</span><span x-show="uploading">{{ __('Uploading...') }}</span>
            </button>
        </div>
    </div>
</div>
