{{-- Akordeon na natywnym <details> (Flux free nie ma akordeonu). $items: lista [pytanie, odpowiedź].
     W odpowiedzi dozwolony prosty HTML z tłumaczeń (bez danych od użytkowników).
     anchor: przedrostek id pozycji (np. faq -> #faq-3); pozycja z adresu (#faq-3) otwiera się sama (wyszukiwarka). --}}
@props(['items' => [], 'name' => null, 'anchor' => null])

<div {{ $attributes->class('divide-y divide-ui-line overflow-hidden rounded-2xl border border-ui-line bg-ui-surface shadow-sm') }}>
    @foreach ($items as [$title, $body])
        <details class="group scroll-mt-24" @if ($name) name="{{ $name }}" @endif
            @if ($anchor) id="{{ $anchor }}-{{ $loop->index }}" x-data x-init="if (location.hash === '#' + $el.id) { $el.open = true; $nextTick(() => $el.scrollIntoView({ block: 'center' })) }" @endif>
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-medium text-ui-ink transition hover:bg-lech-50 group-open:lech-bar group-open:text-white dark:hover:bg-lech-950 [&::-webkit-details-marker]:hidden">
                <span>{{ $title }}</span>
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-ui-raised transition duration-200 group-open:rotate-180 group-open:bg-white/15 group-open:text-white">
                    <x-ui.icon name="chevron-down" variant="micro" />
                </span>
            </summary>
            <div class="border-l-4 border-lech-700 bg-lech-50/40 px-5 pb-5 pt-4 text-sm leading-relaxed text-ui-ink dark:bg-lech-950/30">
                {!! $body !!}
            </div>
        </details>
    @endforeach
</div>
