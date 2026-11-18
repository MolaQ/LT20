{{--
    Kadra drużyny na stronie zespołu (zakładka „Kadra”, 17.11.2026). Dane z Support\TeamSquad::for, sumy liczy Alpine w przeglądarce.
    Sezony: „Wszystkie” stoi na stałe, obok sezony od bieżącego wstecz (przewijane palcem albo strzałkami). Rozgrywki: tylko te,
    w których drużyna grała w wybranym sezonie (w „Wszystkie” ligi razem jako „Liga”), w barwach systemu. Do tego filtr pozycji,
    sortowanie kliknięciem w nagłówek i rozwinięcie zawodnika (sezony i rozgrywki). Niewystawieni zawodnicy są wyszarzeni.
    Zmienne: $squad (TeamSquad::for), $teamName.
--}}
@php
    $styles = collect(\App\Support\CompetitionColors::order())->mapWithKeys(fn ($key) => [$key => \App\Support\CompetitionColors::style($key)])->all();
    $posTone = \App\Enums\PlayerPosition::tones();
    $positions = ['all' => __('Everyone')] + collect(\App\Enums\PlayerPosition::cases())->mapWithKeys(fn ($pos) => [$pos->value => $pos->short()])->all();
    // Kolumny tabeli: klucz => [skrót, opis]; czyste konta i obrona tylko bramkarzom i obrońcom.
    $cols = [
        'fielded' => [__('Fielded'), __('Matchdays in the line-up')],
        'goals' => ['G', __('Goals')],
        'assists' => ['A', __('Assists')],
        'clean_sheets' => [__('CS'), __('Clean sheets')],
        'attack' => [__('Att.'), __('Attack points')],
        'defense' => [__('Def.'), __('Defence points')],
        'cards' => [__('Cards'), __('Yellow and red cards')],
    ];
    $current = collect($squad['seasons'])->firstWhere('current', true)['id'] ?? ($squad['seasons'][0]['id'] ?? 'all');
@endphp
<div class="space-y-4" x-data="{
        data: @js($squad),
        styles: @js($styles),
        tone: @js($posTone),
        season: @js($current),
        set: 'all',
        pos: 'all',
        sort: 'experience',
        dir: -1,
        open: null,
        sums: ['fielded', 'goals', 'assists', 'clean_sheets', 'attack', 'defense', 'yellow', 'red', 'experience'],
        get comps() { return this.season === 'all' ? this.data.all : (this.data.comps[this.season] || {}) },
        pickSeason(id) { this.season = id; if (this.set !== 'all' && ! this.comps[this.set]) this.set = 'all'; this.open = null },
        rowsIn() { return this.data.rows.filter(r => (this.season === 'all' || r[1] === this.season) && (this.set === 'all' || r[2] === this.set)) },
        empty() { return Object.fromEntries(this.sums.map(k => [k, 0])) },
        add(target, r) { this.sums.forEach((k, i) => target[k] += r[i + 3]); return target },
        get totals() {
            const out = {};
            this.rowsIn().forEach(r => this.add(out[r[0]] ??= this.empty(), r));
            return out;
        },
        get list() {
            const totals = this.totals, d = this.dir, key = this.sort;
            const value = p => key === 'name' ? p.name : key === 'number' ? (p.number ?? 999) : key === 'form' ? p.form
                : key === 'cards' ? p.s.yellow + 3 * p.s.red : (this.sheets(p) || ! ['clean_sheets', 'defense'].includes(key) ? p.s[key] : -1);
            return this.data.players
                .filter(p => this.pos === 'all' || p.pos === this.pos)
                .map(p => ({ ...p, s: totals[p.id] ?? this.empty(), idle: ! totals[p.id] }))
                .sort((a, b) => {
                    const x = value(a), y = value(b);
                    const cmp = typeof x === 'string' ? x.localeCompare(y, 'pl') : x - y;
                    return cmp * d || b.s.experience - a.s.experience || a.posRank - b.posRank || (a.number ?? 999) - (b.number ?? 999);
                });
        },
        get tiles() {
            const t = this.empty(); let best = null;
            Object.entries(this.totals).forEach(([id, s]) => {
                this.sums.forEach(k => t[k] += s[k]);
                if (! best || s.experience > best.s.experience) best = { id: +id, s };
            });
            const player = best ? this.data.players.find(p => p.id === best.id) : null;
            return { ...t, comps: new Set(this.rowsIn().map(r => r[2])).size, best: player ? player.name + ' ' + this.exp(best.s.experience) : null };
        },
        detail(id) {
            return this.data.rows.filter(r => r[0] === id && (this.season === 'all' || r[1] === this.season) && (this.set === 'all' || r[2] === this.set))
                .map(r => ({ season: (this.data.seasons.find(s => s.id === r[1]) || {}).label, comp: (this.data.comps[r[1]] || {})[r[2]] || this.data.all[r[2]] || { key: 'league_1', label: r[2] }, s: this.add(this.empty(), r), order: this.data.seasons.findIndex(s => s.id === r[1]) }))
                .sort((a, b) => a.order - b.order);
        },
        sheets(p) { return ['goalkeeper', 'defender'].includes(p.pos) },
        sortBy(key) { if (this.sort === key) { this.dir = -this.dir } else { this.sort = key; this.dir = ['name', 'number'].includes(key) ? 1 : -1 } },
        num(v) { return Number.isInteger(v) ? String(v) : v.toFixed(1).replace('.', ',') },
        exp(v) { return (+v).toFixed(1).replace('.', ',') },
        bar(g) { return g ? Math.max(3, Math.round(Math.min(g[0], 6) / 6 * 18)) : 2 },
        dots(g) { return g ? [...Array(g[1]).fill('bg-amber-400'), ...Array(g[2]).fill('bg-sky-400')].slice(0, 3) : [] },
        tip(g, i) {
            const m = (this.data.formMatches[i] || {}).opponent || '';
            return g ? m + ': ' + this.exp(g[0]) + ' ' + @js(__('exp.')) + (g[1] ? ', G ' + g[1] : '') + (g[2] ? ', A ' + g[2] : '') : m + ': ' + @js(__('not in the squad'));
        },
        scroll(dir) { this.$refs.seasons.scrollBy({ left: dir * 220, behavior: 'smooth' }) },
    }">
    {{-- Sezony: „Wszystkie” na stałe, reszta przewijana od bieżącego wstecz --}}
    <div class="lech-banner relative overflow-hidden rounded-2xl px-4 py-4 sm:px-5">
        <div class="relative flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-display text-xl font-bold text-white">{{ __('Team squad') }}</h2>
                <p class="text-sm text-lech-100">{{ $teamName }} · {{ __('players fielded by this team') }}</p>
            </div>
            <div class="flex min-w-0 max-w-full items-center gap-1 rounded-full bg-white/10 p-1">
                <button type="button" x-on:click="pickSeason('all')" x-bind:class="season === 'all' ? 'bg-white text-lech-800 shadow' : 'text-white/80 hover:text-white'"
                    class="shrink-0 whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-bold transition">{{ __('All seasons') }}</button>
                <button type="button" x-on:click="scroll(-1)" class="hidden shrink-0 rounded-full p-1 text-white/70 hover:bg-white/10 hover:text-white sm:block" aria-label="{{ __('Previous') }}">
                    <x-ui.icon name="chevron-left" variant="micro" />
                </button>
                <div x-ref="seasons" class="flex min-w-0 snap-x gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <template x-for="s in data.seasons" :key="s.id">
                        <button type="button" x-on:click="pickSeason(s.id)" x-bind:class="season === s.id ? 'bg-white text-lech-800 shadow' : 'text-white/80 hover:text-white'"
                            class="shrink-0 snap-start whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-bold transition" x-text="s.label"></button>
                    </template>
                </div>
                <button type="button" x-on:click="scroll(1)" class="hidden shrink-0 rounded-full p-1 text-white/70 hover:bg-white/10 hover:text-white sm:block" aria-label="{{ __('Next') }}">
                    <x-ui.icon name="chevron-right" variant="micro" />
                </button>
            </div>
        </div>
    </div>

    {{-- Sumy dla wybranego sezonu i rozgrywek --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-ui-line bg-ui-surface p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-ui-muted">{{ __('Fielded') }}</p>
            <p class="mt-1 font-display text-2xl font-bold tabular-nums" x-text="tiles.fielded"></p>
            <p class="text-xs text-ui-muted" x-text="@js(__('competitions: '))+ tiles.comps"></p>
        </div>
        <div class="rounded-2xl border border-ui-line bg-ui-surface p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-ui-muted">{{ __('Goals + assists') }}</p>
            <p class="mt-1 font-display text-2xl font-bold tabular-nums" x-text="tiles.goals + tiles.assists"></p>
            <p class="text-xs text-ui-muted"><span x-text="tiles.goals"></span> G · <span x-text="tiles.assists"></span> A</p>
        </div>
        <div class="rounded-2xl border border-ui-line bg-ui-surface p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-ui-muted">{{ __('Clean sheets') }}</p>
            <p class="mt-1 font-display text-2xl font-bold tabular-nums" x-text="tiles.clean_sheets"></p>
            <p class="text-xs text-ui-muted">{{ __('defence points') }}: <span x-text="tiles.defense"></span></p>
        </div>
        <div class="rounded-2xl border border-ui-line bg-ui-surface p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-ui-muted">{{ __('Experience earned') }}</p>
            <p class="mt-1 font-display text-2xl font-bold tabular-nums text-club" x-text="exp(tiles.experience)"></p>
            <p class="truncate text-xs text-ui-muted" x-text="tiles.best ? @js(__('most: ')) + tiles.best : '–'"></p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
        {{-- Rozgrywki (w barwach systemu) i pozycje --}}
        {{-- Telefon (18.11.2026): rozgrywki w siatce po dwa, przyciski tej samej wysokości i szerokości, pozycje na całą szerokość --}}
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ui-line px-4 py-3">
            <div class="grid w-full grid-cols-2 gap-1.5 sm:flex sm:w-auto sm:flex-wrap">
                <button type="button" x-on:click="set = 'all'; open = null" x-bind:class="set === 'all' ? 'bg-club text-club-ink border-club' : 'border-ui-line text-ui-muted hover:text-ui-ink'"
                    class="col-span-2 h-8 truncate rounded-full border px-3 text-xs font-bold transition">{{ __('All competitions') }}</button>
                <template x-for="[key, comp] in Object.entries(comps)" :key="key">
                    <button type="button" x-on:click="set = key; open = null" x-bind:style="styles[comp.key] || styles['league_1']"
                        x-bind:class="set === key ? 'ring-2 ring-amber-400 ring-offset-2 ring-offset-ui-surface' : 'opacity-80 hover:opacity-100'"
                        class="comp-chip h-8 truncate rounded-full px-3 text-xs font-bold transition" x-text="comp.label"></button>
                </template>
            </div>
            <div class="flex w-full gap-1 rounded-full bg-ui-raised p-1 sm:inline-flex sm:w-auto">
                @foreach ($positions as $posKey => $posLabel)
                    <button type="button" x-on:click="pos = '{{ $posKey }}'" title="{{ $posKey === 'all' ? $posLabel : \App\Enums\PlayerPosition::from($posKey)->label() }}"
                        x-bind:class="pos === '{{ $posKey }}' ? '{{ $posKey === 'all' ? 'bg-club text-club-ink' : \App\Enums\PlayerPosition::tones()[$posKey] }} shadow ring-2 ring-white/70 dark:ring-white/30' : '{{ $posKey === 'all' ? 'text-ui-muted hover:text-ui-ink' : \App\Enums\PlayerPosition::tones()[$posKey].' opacity-45 hover:opacity-80' }}'"
                        class="h-7 min-w-9 flex-1 rounded-full px-3 text-xs font-black transition">{{ $posLabel }}</button>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-[11px] font-bold uppercase tracking-wider text-ui-muted dark:bg-white/5">
                    <tr>
                        @foreach (['number' => ['#', __('Number'), 'w-12 text-center ps-4'], 'name' => [__('Footballer'), __('Footballer'), 'text-left'], 'form' => [__('Form'), __('Form: experience from the last :count Lech matches', ['count' => \App\Support\MatchReport::FORM_MATCHES]), 'text-left']] + collect($cols)->map(fn ($c) => [$c[0], $c[1], 'text-right'])->all() + ['experience' => [__('Exp.'), __('Experience earned in this team'), 'text-left pe-4']] as $key => [$short, $long, $align])
                            <th class="{{ $align }} whitespace-nowrap px-2 py-2" title="{{ $long }}">
                                <button type="button" x-on:click="sortBy('{{ $key }}')" class="inline-flex items-center gap-1 uppercase hover:text-club" x-bind:class="sort === '{{ $key }}' && 'text-club'">
                                    {{ $short }}<span x-show="sort === '{{ $key }}'" x-text="dir > 0 ? '▲' : '▼'" class="text-[9px]"></span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <template x-for="p in list" :key="p.id">
                    <tbody class="border-t border-ui-line">
                        <tr x-on:click="open = open === p.id ? null : p.id" x-bind:class="p.idle ? 'text-zinc-400 dark:text-zinc-500' : 'text-ui-ink'"
                            class="cursor-pointer transition hover:bg-lech-50/70 dark:hover:bg-white/5">
                            <td class="py-2 pe-2 ps-4 text-center">
                                <span x-bind:class="tone[p.pos]" x-bind:title="p.posShort"
                                    class="grid size-8 place-items-center rounded-lg text-xs font-black tabular-nums" x-text="p.number ?? '–'"></span>
                            </td>
                            <td class="px-2 py-2">
                                <span class="flex items-center gap-1.5 whitespace-nowrap font-semibold">
                                    <span x-text="p.name"></span>
                                    <template x-if="p.academy"><x-ui.icon name="academic-cap" variant="micro" class="shrink-0 text-lech-600 dark:text-lech-300" /></template>
                                </span>
                            </td>
                            <td class="px-2 py-2">
                                <span class="inline-flex items-center gap-2">
                                    <span class="min-w-7 text-right font-semibold tabular-nums" x-bind:class="! p.form && 'text-zinc-300 dark:text-zinc-600'" x-text="exp(p.form)"></span>
                                    <span class="flex h-10 items-end gap-[2px]" aria-hidden="true">
                                        <template x-for="(g, i) in p.series" :key="i">
                                            <span class="flex w-1.5 flex-col items-center justify-end gap-px" x-bind:title="tip(g, i)">
                                                <template x-for="(dot, j) in dots(g)" :key="j">
                                                    <span class="size-1.5 rounded-full" x-bind:class="dot"></span>
                                                </template>
                                                <span class="w-full rounded-sm" x-bind:class="g ? 'bg-club' : 'bg-zinc-200 dark:bg-zinc-700'" x-bind:style="'height:' + bar(g) + 'px'"></span>
                                            </span>
                                        </template>
                                    </span>
                                </span>
                            </td>
                            @foreach (array_keys($cols) as $key)
                                <td class="px-2 py-2 text-right tabular-nums">
                                    @if ($key === 'cards')
                                        <span class="inline-flex items-center gap-1 text-xs">
                                            <span x-show="p.s.yellow" class="inline-flex items-center gap-0.5"><span class="h-3 w-2 rounded-[2px] bg-yellow-400"></span><span x-text="p.s.yellow"></span></span>
                                            <span x-show="p.s.red" class="inline-flex items-center gap-0.5"><span class="h-3 w-2 rounded-[2px] bg-red-600"></span><span x-text="p.s.red"></span></span>
                                        </span>
                                    @elseif (in_array($key, ['clean_sheets', 'defense'], true))
                                        <span x-show="! sheets(p)" class="text-zinc-300 dark:text-zinc-600">·</span>
                                        <span x-show="sheets(p)" x-bind:class="! p.s.{{ $key }} && 'text-zinc-300 dark:text-zinc-600'" x-text="p.s.{{ $key }}"></span>
                                    @else
                                        <span x-bind:class="! p.s.{{ $key }} && 'text-zinc-300 dark:text-zinc-600'" x-text="p.s.{{ $key }}"></span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="py-2 pe-4 ps-2">
                                <span class="flex items-center gap-2">
                                    <span class="w-10 font-semibold tabular-nums text-club" x-text="exp(p.s.experience)"></span>
                                    <span class="h-1.5 w-16 overflow-hidden rounded-full bg-ui-raised">
                                        <span class="block h-full rounded-full bg-gradient-to-r from-lech-400 to-lech-800" x-bind:style="'width:' + Math.round(100 * p.s.experience / Math.max(1, ...list.map(x => x.s.experience))) + '%'"></span>
                                    </span>
                                </span>
                            </td>
                        </tr>
                        {{-- Rozwinięcie: dorobek w sezonach i rozgrywkach (w ramach filtrów) --}}
                        <tr x-show="open === p.id" x-cloak>
                            <td colspan="11" class="bg-zinc-50/70 px-4 py-3 dark:bg-white/5">
                                <template x-if="detail(p.id).length === 0">
                                    <p class="text-sm text-ui-muted">{{ __('Not fielded by this team in the selected season and competition.') }}</p>
                                </template>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <template x-for="(d, i) in detail(p.id)" :key="i">
                                        <div class="comp-banner rounded-xl px-3 py-2.5" x-bind:style="styles[d.comp.key] || styles['league_1']">
                                            <p class="relative z-10 flex items-center justify-between gap-2 text-xs font-bold uppercase tracking-wide">
                                                <span class="truncate" x-text="d.comp.label"></span><span class="shrink-0 opacity-80" x-text="d.season"></span>
                                            </p>
                                            <p class="relative z-10 mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-sm tabular-nums">
                                                <span><span x-text="d.s.fielded"></span> {{ __('Fielded') }}</span>
                                                <span x-show="d.s.goals">⚽ <span x-text="d.s.goals"></span></span>
                                                <span x-show="d.s.assists">A <span x-text="d.s.assists"></span></span>
                                                <span x-show="d.s.clean_sheets">{{ __('CS') }} <span x-text="d.s.clean_sheets"></span></span>
                                                <span class="ms-auto font-bold" x-text="'+' + exp(d.s.experience)"></span>
                                            </p>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </template>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-ui-line px-4 py-2.5 text-xs text-ui-muted">
            <span>{{ __('Click a column to sort, click a player to see his seasons and competitions in this team.') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="text-zinc-300">·</span>{{ __('Clean sheets and defence points count only for goalkeepers and defenders') }}</span>
            <span>{{ __('Grey: not fielded in the selected season and competition.') }}</span>
        </div>
    </div>
</div>
