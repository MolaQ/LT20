<?php

use App\Enums\CompetitionType;
use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Support\MatchdayGraphics;
use App\Support\MatchdayInsights;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Grafiki kolejki do mediów społecznościowych (Promocja → Grafiki kolejki). Przed meczem: zapowiedź w barwach
 * drużyn, pytania (jedna grafika na zestaw), typy kibiców, hity kolejki w grze i ostatnia prosta. Po meczu: wynik
 * z najlepszymi strzelcami, „pod prąd” i wyścig o nagrody. Tylko odczyt; obrazki robi przycisk udostępniania.
 */
new class extends Component
{
    #[Url(as: 'matchday', except: 0)]
    public int $matchdayId = 0;

    public function mount(): void
    {
        if (! Matchday::whereKey($this->matchdayId)->exists()) {
            // Domyślnie najbliższa nierozegrana kolejka bieżącego sezonu, a bez niej ostatnia.
            $season = Season::current() ?? Season::orderByDesc('number')->first();
            $query = Matchday::where('season_id', $season?->id ?? 0);
            $this->matchdayId = (int) ((clone $query)->where('status', '!=', MatchdayStatus::Played->value)->orderBy('number')->value('id')
                ?? $query->orderByDesc('number')->value('id'));
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('Matchday graphics'));
    }

    #[Computed]
    public function matchdays()
    {
        $season = Season::current() ?? Season::orderByDesc('number')->first();

        return Matchday::where('season_id', $season?->id ?? 0)->orderBy('number')->get(['id', 'number', 'opponent']);
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return Matchday::with('season')->find($this->matchdayId);
    }

    #[Computed]
    public function teams(): array
    {
        return MatchdayGraphics::teams($this->matchday);
    }

    #[Computed]
    public function tips(): array
    {
        return MatchdayInsights::tips($this->matchday);
    }
}; ?>

@php
    $md = $this->matchday;
    $brand = config('club.brand.prefix') . config('club.brand.suffix');
    $site = parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name');
    $hashtag = config('club.hashtag') ? ' #' . config('club.hashtag') : '';
    // Pasy barw: równe odcinki w poziomie albo w pionie.
    $stripe = function (array $colors, string $dir = '90deg'): string {
        $n = count($colors);
        $parts = [];
        foreach (array_values($colors) as $i => $c) {
            $parts[] = $c . ' ' . round($i * 100 / $n, 2) . '% ' . round(($i + 1) * 100 / $n, 2) . '%';
        }

        return "linear-gradient($dir, " . implode(', ', $parts) . ')';
    };
@endphp

<div class="flex w-full flex-col gap-6" x-data="{ fmt: 'square' }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <x-ui.heading size="xl" level="1">{{ __('Matchday graphics') }}</x-ui.heading>
            <x-ui.text>{{ __('Ready images for social media. The data updates itself: download the image or post it on X.') }}</x-ui.text>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            @if ($this->matchdays->isNotEmpty())
                <div class="w-full sm:w-64">
                    <x-ui.select wire:model.live="matchdayId" :label="__('Matchday')">
                        @foreach ($this->matchdays as $option)
                            <x-ui.select.option :value="$option->id">{{ $option->number }}. {{ filled($option->opponent) ? $option->opponent : '–' }}</x-ui.select.option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif
            <div class="inline-flex overflow-hidden rounded-lg border border-ui-line text-sm" role="group" aria-label="{{ __('Format') }}">
                <button type="button" class="px-3 py-2" :class="fmt === 'square' ? 'bg-accent text-accent-foreground' : 'bg-ui-surface'" @click="fmt = 'square'">{{ __('Square 1:1') }}</button>
                <button type="button" class="px-3 py-2" :class="fmt === 'story' ? 'bg-accent text-accent-foreground' : 'bg-ui-surface'" @click="fmt = 'story'">{{ __('Story 9:16') }}</button>
            </div>
        </div>
    </div>

    @if (!$md || !$md->isFilled())
        <x-ui.card>
            <x-ui.text>{{ __('Choose a matchday with a rival and a kickoff time.') }}</x-ui.text>
        </x-ui.card>
    @else
        @php
            $teams = $this->teams;
            $home = $teams['home'];
            $away = $teams['away'];
            $tips = $this->tips;
            $played = $md->lech_goals !== null && $md->opponent_goals !== null;
            $when = trim(($md->competitionLabel() ?? '') . ' · ' . $md->kickoff_at->translatedFormat('l H:i'), ' ·');
            $fixture = $home['name'] . ' – ' . $away['name'];
            $club = (string) config('club.short_name');
        @endphp

        {{-- Nagłówek każdej grafiki: marka gry i numer kolejki --}}
        @php
            $top = '<div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-widest"><span class="rounded bg-black/40 px-2 py-1">' . e($brand) . '</span><span class="rounded bg-black/40 px-2 py-1">' . e(__('Matchday :n', ['n' => $md->number])) . '</span></div>';
            $cta = '<div class="text-center text-[11px] font-semibold text-amber-300">' . e(__('Tip at :site', ['site' => $site])) . '</div>';
        @endphp

        <section class="space-y-3">
            <x-ui.heading size="lg" level="2">{{ __('Before the match') }}</x-ui.heading>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">

                {{-- Zapowiedź w barwach drużyn: dwie połowy po skosie i pasy barw dodatkowych wzdłuż cięcia --}}
                <x-graphic-card :title="__('Matchday preview')" :when="__('Before the match')" background="#061331"
                    :text="__(':fixture, :when. Tip the score and answer the questions at :site.', ['fixture' => $fixture, 'when' => $when, 'site' => $site]) . $hashtag">
                    <div class="absolute inset-0" style="background: {{ $home['colors'][0] }}; clip-path: polygon(0 0, 62% 0, 38% 100%, 0 100%)"></div>
                    <div class="absolute inset-0" style="background: {{ $away['colors'][0] }}; clip-path: polygon(62% 0, 100% 0, 100% 100%, 38% 100%)"></div>
                    <div class="absolute inset-0" style="background: {{ $stripe(array_slice($home['colors'], 1) ?: [$home['colors'][0]], '180deg') }}; clip-path: polygon(56% 0, 60% 0, 36% 100%, 32% 100%)"></div>
                    <div class="absolute inset-0" style="background: {{ $stripe(array_slice($away['colors'], 1) ?: [$away['colors'][0]], '180deg') }}; clip-path: polygon(64% 0, 68% 0, 44% 100%, 40% 100%)"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-black/25 via-transparent to-black/60"></div>
                    <div class="relative flex h-full flex-col p-4">
                        {!! $top !!}
                        @php
                            // Jedna wielkość nazw dla obu drużyn, według najdłuższego słowa (słowa się nie dzielą).
                            $longest = max(array_map('mb_strlen', preg_split('/\s+/u', $home['name'] . ' ' . $away['name']) ?: ['']));
                            $nameSize = match (true) { $longest <= 6 => 'text-lg', $longest <= 8 => 'text-base', $longest <= 10 => 'text-sm', default => 'text-xs' };
                        @endphp
                        <div class="my-auto flex items-center justify-between">
                            @foreach ([$home, $away] as $i => $team)
                                {{-- Bez herbów: duża nazwa w barwie napisu dla tła drużyny i pasek jej barw --}}
                                <div class="w-[40%] text-center">
                                    <div class="{{ $nameSize }} font-black uppercase leading-none tracking-tight break-words drop-shadow" style="color: {{ $team['ink'] }}">{{ $team['name'] }}</div>
                                    <div class="mx-auto mt-2 h-1.5 w-14 rounded-full ring-1 ring-black/20" style="background: {{ $stripe($team['colors']) }}"></div>
                                </div>
                                @if ($i === 0)
                                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-lech-950 text-xs font-black text-amber-300 ring-2 ring-amber-300">VS</div>
                                @endif
                            @endforeach
                        </div>
                        <div class="rounded-lg bg-lech-950/90 p-2 text-center">
                            {{-- Rozgrywki i data; godzina tylko w linii o typowaniu (typowanie trwa do pierwszego gwizdka) --}}
                            <div class="text-[10px] uppercase tracking-wide text-lech-200">{{ trim(($md->competitionLabel() ?? '') . ' · ' . $md->kickoff_at->translatedFormat('l j.m'), ' ·') }}</div>
                            <div class="mt-0.5 text-xs font-bold">{{ __('Tips until :time', ['time' => $md->kickoff_at->translatedFormat('D H:i')]) }}</div>
                            <div class="text-[11px] text-lech-100">{{ __('score · questions · line-ups') }}</div>
                            <div class="mt-1">{!! $cta !!}</div>
                        </div>
                    </div>
                </x-graphic-card>

                {{-- Pytania: jedna grafika na zestaw (liga, puchar, puchary europejskie, Liga Legend) --}}
                @foreach (MatchdayGraphics::questions($md) as $set => $questions)
                    @php
                        $setLabel = CompetitionType::tryFrom($set)?->label() ?? $set;
                        $closed = !$md->isOpenForTips();
                    @endphp
                    <x-graphic-card :title="__('Questions') . ': ' . $setLabel" :when="__('Before the match')"
                        :text="__('Matchday :n questions (:set): yes or no? Answer at :site.', ['n' => $md->number, 'set' => $setLabel, 'site' => $site]) . $hashtag"
                        :badge="$closed ? __('Tipping is closed, so the image shows how the fans answered.') : ''">
                        <div class="flex h-full flex-col p-4">
                            {!! $top !!}
                            <div class="mt-3 text-lg font-black leading-tight">{{ trans_choice(':n question|:n questions', count($questions), ['n' => count($questions)]) }}<br><span class="text-sm font-bold text-lech-200">{{ $setLabel }}</span></div>
                            <div class="my-auto space-y-1.5">
                                @foreach ($questions as $i => $q)
                                    <div class="rounded-lg bg-white/10 px-2 py-1.5 text-xs">
                                        <div class="flex items-center gap-2"><span class="grid size-5 shrink-0 place-items-center rounded-full bg-amber-400 text-[10px] font-bold text-lech-950">{{ $i + 1 }}</span><span class="leading-tight">{{ $q['text'] }}</span></div>
                                        @if ($closed && $q['yes'] !== null)
                                            <div class="mt-1 flex h-1.5 overflow-hidden rounded-full bg-white/15"><div class="bg-emerald-400" style="width: {{ $q['yes'] }}%"></div></div>
                                            <div class="mt-0.5 flex justify-between text-[10px] text-lech-200"><span>{{ __('Yes') }} {{ $q['yes'] }}%</span><span>{{ __('No') }} {{ 100 - $q['yes'] }}%</span></div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            {!! $closed ? '' : $cta !!}
                        </div>
                    </x-graphic-card>
                @endforeach

                {{-- Typy kibiców: paski w barwach drużyn (jasne barwy z obwódką) --}}
                @php
                    $count = max(1, $tips['count']);
                    $lechSide = $md->is_home ? $home : $away;
                    $rivalSide = $md->is_home ? $away : $home;
                    $bars = [
                        [$club, (int) round($tips['lech'] * 100 / $count), $lechSide['colors'][0]],
                        [__('A draw'), (int) round($tips['draw'] * 100 / $count), '#a3a3a3'],
                        [$md->opponent, (int) round($tips['opponent'] * 100 / $count), $rivalSide['colors'][0]],
                    ];
                    $popular = $tips['popular'][0] ?? null;
                @endphp
                <x-graphic-card :title="__('How the fans tip')" :when="__('Before the match')"
                    :text="__('How do the fans tip :fixture? :lech% back :club, :draw% a draw. Add your tip at :site.', ['fixture' => $fixture, 'lech' => $bars[0][1], 'club' => $club, 'draw' => $bars[1][1], 'site' => $site]) . $hashtag"
                    :badge="$tips['count'] === 0 ? __('No tips yet.') : ''">
                    <div class="flex h-full flex-col p-4">
                        {!! $top !!}
                        <div class="mt-3 text-lg font-black leading-tight">{{ __('How do the fans tip?') }}</div>
                        <div class="my-auto space-y-2">
                            @foreach ($bars as [$label, $percent, $color])
                                <div class="text-xs">
                                    <div class="flex justify-between"><span>{{ $label }}</span><b>{{ $percent }}%</b></div>
                                    <div class="mt-0.5 h-2.5 rounded-full bg-white/10">
                                        <div class="h-2.5 rounded-full" style="width: {{ max(2, $percent) }}%; background: {{ $color }}; {{ MatchdayGraphics::isLight($color) ? 'box-shadow: inset 0 0 0 1px rgba(255,255,255,.9), 0 0 0 1px rgba(0,0,0,.35)' : '' }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center text-[11px]">
                            <div class="rounded-lg bg-white/10 p-1.5">{{ __('Most common tip') }}<br><b class="text-base">{{ $popular['score'] ?? '–' }}</b>@if ($popular) <span class="text-lech-200">{{ (int) round($popular['count'] * 100 / $count) }}%</span>@endif</div>
                            <div class="rounded-lg bg-white/10 p-1.5">{{ __('Tips') }}<br><b class="text-base">{{ number_format($tips['count'], 0, ',', ' ') }}</b></div>
                        </div>
                    </div>
                </x-graphic-card>

                {{-- Hity kolejki w grze --}}
                @php $hits = MatchdayGraphics::hits($md); @endphp
                <x-graphic-card :title="__('Top games of the matchday')" :when="__('Before the match')"
                    :text="__('Top games of matchday :n in the game: :games. Follow them at :site.', ['n' => $md->number, 'games' => collect($hits)->map(fn ($h) => $h['home'] . ' – ' . $h['away'])->implode(', '), 'site' => $site]) . $hashtag"
                    :badge="$hits === [] ? __('No fixtures in this matchday yet.') : __('Chosen automatically: the top league clash, the most interesting game of the lower leagues, the strongest cup pair and the Legends League top players.')">
                    <div class="flex h-full flex-col p-4">
                        {!! $top !!}
                        <div class="mt-3 text-lg font-black leading-tight">{{ __('Top games of the matchday') }}</div>
                        <div class="my-auto space-y-1.5">
                            @foreach ($hits as $hit)
                                <div class="rounded-lg bg-white/10 px-2 py-1.5 text-xs">
                                    <div class="text-[10px] uppercase tracking-wider text-lech-200">{{ $hit['competition'] }}</div>
                                    <div class="flex items-center justify-between gap-1 font-semibold">
                                        <span class="truncate">{{ $hit['home'] }} <span class="font-normal text-lech-200">{{ $hit['home_note'] }}</span></span>
                                        <span class="shrink-0 text-amber-300">vs</span>
                                        <span class="truncate text-right">{{ $hit['away'] }} <span class="font-normal text-lech-200">{{ $hit['away_note'] }}</span></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-graphic-card>

                {{-- Ostatnia prosta: czas do końca typowania i liczba typów --}}
                @php
                    $progress = MatchdayGraphics::progress($md);
                    $left = $md->isOpenForTips() ? now()->diff($md->kickoff_at) : null;
                    $hours = $left ? $left->days * 24 + $left->h : 0;
                @endphp
                <x-graphic-card :title="__('Final stretch')" :when="__('Match day')"
                    :text="__('The clock is ticking: :tips tips are in, :missing players are still missing. Tip :fixture at :site.', ['tips' => $progress['tips'], 'missing' => $progress['missing'], 'fixture' => $fixture, 'site' => $site]) . $hashtag">
                    <div class="flex h-full flex-col p-4">
                        {!! $top !!}
                        <div class="my-auto text-center">
                            @if ($left)
                                <div class="text-xs uppercase tracking-widest text-lech-200">{{ __('Until tipping closes') }}</div>
                                <div class="mt-2 flex justify-center gap-2">
                                    @foreach ([[$hours, __('hrs')], [$left->i, __('min')]] as [$value, $unit])
                                        <div class="rounded-lg bg-white/10 px-3 py-2"><div class="text-3xl font-black">{{ str_pad((string) $value, 2, '0', STR_PAD_LEFT) }}</div><div class="text-[10px] text-lech-200">{{ $unit }}</div></div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-sm font-bold">{{ __('Tipping is closed') }}</div>
                            @endif
                            <div class="mt-4 text-sm">{{ __(':n tips are already in', ['n' => number_format($progress['tips'], 0, ',', ' ')]) }}</div>
                            <div class="mx-auto mt-1 h-2 w-4/5 rounded-full bg-white/10"><div class="h-2 rounded-full bg-amber-400" style="width: {{ $progress['percent'] }}%"></div></div>
                            <div class="mt-1 text-[11px] text-lech-200">{{ __(':percent% of players · :missing still missing', ['percent' => $progress['percent'], 'missing' => $progress['missing']]) }}</div>
                        </div>
                        {!! $cta !!}
                    </div>
                </x-graphic-card>
            </div>
        </section>

        <section class="space-y-3">
            <x-ui.heading size="lg" level="2">{{ __('After the match') }}</x-ui.heading>
            @if (!$played)
                <x-ui.text>{{ __('These images fill in after the result is entered.') }}</x-ui.text>
            @endif
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                @if ($played)
                    {{-- Wynik, trafienia i najwięcej goli w kolejce --}}
                    @php
                        $score = $md->is_home ? $md->lech_goals . ' : ' . $md->opponent_goals : $md->opponent_goals . ' : ' . $md->lech_goals;
                        $scorers = MatchdayGraphics::scorers($md);
                    @endphp
                    <x-graphic-card :title="__('After the matchday')" :when="__('After the match')"
                        :text="__(':fixture :score. The exact score was hit by :exact players. Full results at :site.', ['fixture' => $fixture, 'score' => $score, 'exact' => (int) $tips['exact_count'], 'site' => $site]) . $hashtag">
                        <div class="flex h-full flex-col p-4">
                            {!! $top !!}
                            <div class="mt-2 text-center text-[11px] uppercase tracking-widest text-lech-200">{{ $fixture }}</div>
                            <div class="text-center text-4xl font-black">{{ $score }}</div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-center text-[11px]">
                                <div class="rounded-lg bg-white/10 p-1">{{ __('Exact score') }}<br><b class="text-sm">{{ (int) $tips['exact_count'] }}</b></div>
                                <div class="rounded-lg bg-white/10 p-1">{{ __('Correct outcome') }}<br><b class="text-sm">{{ (int) $tips['outcome'] }}%</b></div>
                            </div>
                            @if ($scorers !== [])
                                <div class="mt-auto">
                                    <div class="text-[10px] uppercase tracking-wider text-lech-200">{{ __('Most goals in the matchday') }}</div>
                                    @foreach ($scorers as $i => $row)
                                        <div class="flex justify-between text-xs"><span class="truncate">{{ $i + 1 }}. {{ $row['name'] }}</span><b>{{ $row['goals'] }}</b></div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </x-graphic-card>

                    {{-- Pod prąd: zwycięzcę typowała mniejszość, a ktoś trafił --}}
                    @php $against = MatchdayGraphics::against($md); @endphp
                    @if ($against)
                        <x-graphic-card :title="__('Against the tide')" :when="__('After the match')"
                            :text="__('Only :percent% tipped :outcome, and :names got it right. Respect!', ['percent' => $against['percent'], 'outcome' => $against['outcome'], 'names' => implode(', ', $against['names'])]) . $hashtag">
                            <div class="flex h-full flex-col p-4">
                                {!! $top !!}
                                <div class="mt-3 text-lg font-black leading-tight">{{ __('Against the tide') }}<br><span class="text-amber-300">{{ __('and they got it right!') }}</span></div>
                                <div class="my-auto">
                                    <div class="rounded-lg bg-white/10 p-2 text-xs">{{ __('Only :percent% tipped :outcome.', ['percent' => $against['percent'], 'outcome' => $against['outcome']]) }}<br>{{ $against['exact'] ? __('The exact score :score was hit by:', ['score' => $score]) : __('The winner was hit by:') }}</div>
                                    <div class="mt-2 space-y-0.5 text-sm font-semibold">
                                        @foreach ($against['names'] as $name)
                                            <div class="truncate">{{ $name }}</div>
                                        @endforeach
                                        @if ($against['more'] > 0)
                                            <div class="text-xs font-normal text-lech-200">{{ __('and :n more', ['n' => $against['more']]) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </x-graphic-card>
                    @endif
                @endif

                {{-- Odznaki kolejki: kto zdobył nową odznakę albo wyższy stopień po tej kolejce --}}
                @php $earnedBadges = \App\Support\Badges::earnedIn($md); @endphp
                @if ($earnedBadges !== [])
                    @php $shown = array_slice($earnedBadges, 0, 6); @endphp
                    <x-graphic-card :title="__('Badges of the matchday')" :when="__('After the match')"
                        :text="__('New badges after matchday :n: :list. Check yours at :site.', ['n' => $md->number, 'list' => collect($shown)->map(fn ($b) => $b['name'] . ' (' . $b['label'] . ')')->implode(', '), 'site' => $site]) . $hashtag"
                        :badge="count($earnedBadges) > 6 ? __('The image shows 6 of :n badges.', ['n' => count($earnedBadges)]) : ''">
                        <div class="flex h-full flex-col p-4">
                            {!! $top !!}
                            <div class="mt-3 text-lg font-black leading-tight">{{ __('Badges of the matchday') }}</div>
                            <div class="my-auto space-y-1.5">
                                @foreach ($shown as $earned)
                                    <div class="flex items-center gap-2 rounded-lg bg-white/10 px-2 py-1 text-xs">
                                        <x-badge-icon :badge="$earned['badge']" :tier="$earned['tier']" :icon="$earned['icon']" class="size-7" />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-semibold">{{ $earned['name'] }}</div>
                                            <div class="truncate text-[10px] text-lech-200">{{ $earned['label'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            {!! $cta !!}
                        </div>
                    </x-graphic-card>
                @endif

                {{-- Wyścig o nagrody: liderzy po ostatniej rozliczonej kolejce --}}
                @php $leaders = $md->season ? MatchdayGraphics::awardLeaders($md->season) : []; @endphp
                @if ($leaders !== [])
                    <x-graphic-card :title="__('Race for the awards')" :when="__('After the match')"
                        :text="__('Race for the awards: :list. Check your place at :site.', ['list' => collect($leaders)->map(fn ($l) => $l['label'] . ' – ' . $l['name'])->implode(', '), 'site' => $site]) . $hashtag">
                        <div class="flex h-full flex-col p-4">
                            {!! $top !!}
                            <div class="mt-3 text-lg font-black leading-tight">{{ __('Race for the awards') }}</div>
                            <div class="my-auto space-y-1.5">
                                @foreach ($leaders as $leader)
                                    <div class="rounded-lg bg-white/10 px-2 py-1.5 text-xs">
                                        <div class="text-[10px] uppercase tracking-wider text-amber-300">★ {{ $leader['label'] }}</div>
                                        <div class="flex justify-between gap-2 font-semibold"><span class="truncate">{{ $leader['name'] }}</span><span class="shrink-0 font-normal text-lech-200">{{ $leader['value'] }}</span></div>
                                    </div>
                                @endforeach
                            </div>
                            {!! $cta !!}
                        </div>
                    </x-graphic-card>
                @endif
            </div>
        </section>
    @endif
</div>
