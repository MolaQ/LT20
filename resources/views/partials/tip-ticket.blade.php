{{--
    Bilet: typ wyniku i odcinek na każde rozgrywki gracza w ich barwach (wystawieni zawodnicy, pytania: OF / DEF / pominięte).
    Klik w odcinek otwiera te rozgrywki (skład i pytania). Typ i skład zapisują się same przy każdej zmianie,
    „Skasuj bilet” tylko to potwierdza. Przycisk w nagłówku robi z biletu obrazek (x-share-image, data-share-target).
--}}
@php $ticket = $this->ticket; @endphp
<section x-data="{ punched: false }" x-on:ticket-punched.window="punched = true" data-share-target
    class="relative flex flex-col gap-2.5 rounded-[20px] border border-ui-line bg-ui-surface p-4 [mask:radial-gradient(circle_12px_at_0_7.3rem,transparent_98%,#000)_left/51%_100%_no-repeat,radial-gradient(circle_12px_at_100%_7.3rem,transparent_98%,#000)_right/51%_100%_no-repeat]">
    <div class="flex h-8 items-center gap-2">
        <img src="{{ asset(config('club.logo')) }}" alt="" class="size-7">
        <b class="font-display text-xl">{{ __('Your ticket') }}</b>
        <span class="ms-auto text-xs text-ui-muted">{{ __('Matchday :number', ['number' => $md->number]) }}</span>
        <x-share-image :title="__('Your ticket')" class="text-ui-muted" />
    </div>
    <div class="ui-num py-1 text-center text-[40px] font-extrabold leading-none">
        {{ mb_strtoupper(config('club.short_name')) }} {{ $this->tip ? $this->tip->score() : '–:–' }} {{ mb_strtoupper((string) $md->opponent) }}
    </div>
    {{-- Perforacja: wycięcia po bokach (maska) i przerywana linia --}}
    <div class="-mx-4 border-t-2 border-dashed border-ui-line"></div>

    <div class="grid gap-2 sm:grid-cols-2">
    @forelse ($ticket as $line)
        <button type="button" wire:click="openSet('{{ $line['set'] }}')" wire:key="stub-{{ $line['set'] }}" style="{{ $line['style'] }}"
            x-on:click="if (window.innerWidth >= 1024) document.getElementById('tip-competitions')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
            class="comp-chip flex w-full flex-col gap-1 rounded-xl px-3 py-2 text-start transition hover:shadow-md active:scale-[.99]">
            <span class="flex w-full items-center justify-between gap-2">
                <span class="truncate font-display text-sm font-bold">{{ $line['label'] }}</span>
                <span class="flex shrink-0 items-center gap-0.5 text-xs font-semibold tabular-nums"><x-ui.icon name="user-group" variant="micro" />{{ count($line['players']) }}/{{ $line['max'] }}</span>
            </span>
            <span class="w-full truncate text-[13px] opacity-90">{{ $line['players'] ? implode(', ', $line['players']) : __('No players fielded yet') }}</span>
            @if ($line['questions'])
                <span class="flex w-full flex-wrap items-center gap-1">
                    @foreach ($line['questions'] as $side)
                        <span @class([
                            'rounded-full px-1.5 py-px font-display text-[10px] font-bold tracking-wider',
                            'bg-gold text-black/80' => $side === 'attack',
                            'bg-good text-white' => $side === 'defense',
                            'border border-current opacity-40' => $side === null,
                        ])>{{ match ($side) { 'attack' => 'OF', 'defense' => 'DEF', default => '–' } }}</span>
                    @endforeach
                    <span class="ms-auto text-[11px] opacity-80">{{ __('Questions: :answered/:count', ['answered' => $line['summary']['answered'], 'count' => count($line['questions'])]) }}</span>
                </span>
            @endif
        </button>
    @empty
        <div class="text-sm text-ui-muted">{{ __('No players fielded.') }}</div>
    @endforelse
    </div>
    <p class="text-center text-xs text-ui-muted">{{ __('Score, squads and answers save automatically.') }}</p>

    <span x-show="punched || {{ $this->tip && !$this->isOpen ? 'true' : 'false' }}" x-cloak
        class="animate-ui-stamp pointer-events-none absolute end-4 top-12 rounded-lg border-[3px] border-good bg-ui-surface/70 px-2.5 py-0.5 font-display text-xl font-extrabold tracking-wider text-good">{{ __('PUNCHED') }}</span>
</section>

@if ($this->isOpen && $this->tip)
    <x-ui.button wire:click="punch">{{ __('Punch the ticket') }}</x-ui.button>
@endif
