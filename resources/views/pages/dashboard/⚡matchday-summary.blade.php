<?php

use App\Enums\CompetitionType;
use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\News;
use App\Models\Season;
use App\Support\Audit;
use App\Support\MatchdayGraphics;
use App\Support\MatchdayInsights;
use App\Support\MatchdaySummary;
use App\Support\NewsHtml;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Podsumowanie rozliczonej kolejki (Promocja → Podsumowanie kolejki): gotowy artykuł do zapisania jako szkic
 * aktualności i grafiki po meczu do mediów społecznościowych (wynik, typy kibiców, pytania, mokre szmaty,
 * odpadli z Ligi Legend, pod prąd, odznaki, wyścig o nagrody). Dane liczy App\Support\MatchdaySummary.
 */
new class extends Component {
    #[Url(as: 'matchday', except: 0)]
    public int $matchdayId = 0;

    public function mount(): void
    {
        if (!$this->matchdays->contains('id', $this->matchdayId)) {
            // Domyślnie ostatnia rozliczona kolejka.
            $this->matchdayId = (int) ($this->matchdays->last()?->id ?? 0);
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('Matchday summary'));
    }

    /** Rozliczone kolejki bieżącego sezonu (a bez niego ostatniego). */
    #[Computed]
    public function matchdays()
    {
        $season = Season::current() ?? Season::orderByDesc('number')->first();

        return Matchday::where('season_id', $season?->id ?? 0)->where('status', MatchdayStatus::Played->value)
            ->whereNotNull('lech_goals')->whereNotNull('opponent_goals')
            ->orderBy('number')->get(['id', 'number', 'opponent']);
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return Matchday::with('season')->where('status', MatchdayStatus::Played->value)->find($this->matchdayId);
    }

    #[Computed]
    public function article(): ?array
    {
        return $this->matchday ? MatchdaySummary::article($this->matchday) : null;
    }

    /** Zapisuje artykuł jako szkic aktualności (bez daty publikacji) i przechodzi do listy szkiców. */
    public function createDraft(): void
    {
        abort_unless(auth()->user()?->can(\App\Enums\Permission::NewsCreate->value), 403);
        $article = $this->article;
        if (!$article) {
            return;
        }

        $news = News::create(['user_id' => auth()->id(), 'title' => $article['title'], 'body' => $article['body'], 'published_at' => null]);
        Audit::log('news.created', null, [], $news->only(['title', 'published_at']), $news->title);

        $this->redirectRoute('dashboard.news', ['status' => 'draft'], navigate: true);
    }
}; ?>

@php
    $md = $this->matchday;
    $brand = config('club.brand.prefix') . config('club.brand.suffix');
    $site = parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name');
    $hashtag = config('club.hashtag') ? ' #' . config('club.hashtag') : '';
    $canNews = auth()->user()?->can(\App\Enums\Permission::NewsCreate->value);
@endphp

<div class="flex w-full flex-col gap-6" x-data="{ fmt: 'square' }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <x-ui.heading size="xl" level="1">{{ __('Matchday summary') }}</x-ui.heading>
            <x-ui.text>{{ __('An article for the website and images for social media after a settled matchday.') }}</x-ui.text>
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

    @if (!$md)
        <x-ui.card>
            <x-ui.text>{{ __('No settled matchday in this season yet.') }}</x-ui.text>
        </x-ui.card>
    @else
        @php
            $tips = MatchdayInsights::tips($md);
            $fixture = $md->fixture;
            $club = (string) config('club.short_name');
            $article = $this->article;
            $top = '<div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-widest"><span class="rounded bg-black/40 px-2 py-1">' . e($brand) . '</span><span class="rounded bg-black/40 px-2 py-1">' . e(__('Matchday :n', ['n' => $md->number])) . '</span></div>';
            $cta = '<div class="text-center text-[11px] font-semibold text-amber-300">' . e(__('Tip at :site', ['site' => $site])) . '</div>';
        @endphp

        {{-- Artykuł: podgląd i zapis jako szkic aktualności --}}
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <x-ui.heading size="lg" level="2">{{ __('Article') }}</x-ui.heading>
                @if ($canNews)
                    <x-ui.button variant="primary" icon="newspaper" wire:click="createDraft" wire:loading.attr="disabled">{{ __('Create a news draft') }}</x-ui.button>
                @endif
            </div>
            <x-ui.card class="space-y-3">
                <h3 class="text-xl font-black">{{ $article['title'] }}</h3>
                <div class="news-body max-w-3xl space-y-3 text-sm leading-relaxed [&_h2]:mt-4 [&_h2]:text-lg [&_h2]:font-bold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                    {!! NewsHtml::render($article['body']) !!}
                </div>
                <x-ui.text class="text-xs">{{ __('The draft lands in News, where you can edit it and publish it.') }}</x-ui.text>
            </x-ui.card>
        </section>

        <section class="space-y-3">
            <x-ui.heading size="lg" level="2">{{ __('Images') }}</x-ui.heading>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
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

                {{-- Typy kibiców kontra rzeczywistość: najczęstsze wyniki z procentami, trafiony podświetlony --}}
                @php $scores = MatchdaySummary::scores($md); $score = MatchdaySummary::score($md); @endphp
                @if ($scores !== [])
                    <x-graphic-card :title="__('Fans vs reality')" :when="__('After the match')"
                        :text="__('Fans vs reality: the most popular tip was :top (:percent%), the match ended :score.', ['top' => $scores[0]['score'], 'percent' => $scores[0]['percent'], 'score' => $md->lech_goals . ':' . $md->opponent_goals]) . $hashtag">
                        <div class="flex h-full flex-col p-4">
                            {!! $top !!}
                            <div class="mt-3 text-lg font-black leading-tight">{{ __('Fans vs reality') }}</div>
                            <div class="text-xs text-lech-200">{{ $fixture }} {{ $score }}</div>
                            <div class="my-auto space-y-1.5">
                                @foreach ($scores as $row)
                                    <div class="text-xs">
                                        <div class="flex justify-between"><span @class(['font-bold text-amber-300' => $row['hit']])>{{ $row['score'] }} {{ $row['hit'] ? '✓' : '' }}</span><b>{{ $row['percent'] }}%</b></div>
                                        <div class="mt-0.5 h-2 rounded-full bg-white/10"><div @class(['h-2 rounded-full', 'bg-amber-400' => $row['hit'], 'bg-sky-400' => !$row['hit']]) style="width: {{ max(2, $row['percent']) }}%"></div></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-graphic-card>
                @endif

                {{-- Odpowiedzi na pytania: jedna grafika na zestaw, z poprawną odpowiedzią i odsetkiem trafień --}}
                @foreach (MatchdayGraphics::questions($md) as $set => $questions)
                    @php $setLabel = CompetitionType::tryFrom($set)?->label() ?? $set; @endphp
                    <x-graphic-card :title="__('Answers') . ': ' . $setLabel" :when="__('After the match')"
                        :text="__('Matchday :n questions (:set): how did you do? Check at :site.', ['n' => $md->number, 'set' => $setLabel, 'site' => $site]) . $hashtag">
                        <div class="flex h-full flex-col p-4">
                            {!! $top !!}
                            <div class="mt-3 text-lg font-black leading-tight">{{ __('Answers') }}<br><span class="text-sm font-bold text-lech-200">{{ $setLabel }}</span></div>
                            <div class="my-auto space-y-1.5">
                                @foreach ($questions as $q)
                                    @php $right = $q['yes'] === null || $q['correct'] === null ? null : ($q['correct'] ? $q['yes'] : 100 - $q['yes']); @endphp
                                    <div class="rounded-lg bg-white/10 px-2 py-1.5 text-xs">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="leading-tight">{{ $q['text'] }}</span>
                                            @if ($q['correct'] !== null)
                                                <span @class(['shrink-0 rounded px-1.5 text-[10px] font-black', 'bg-emerald-400 text-lech-950' => $q['correct'], 'bg-red-400 text-white' => !$q['correct']])>{{ $q['correct'] ? __('Yes') : __('No') }}</span>
                                            @endif
                                        </div>
                                        @if ($right !== null)
                                            <div class="mt-0.5 text-[10px] text-lech-200">{{ __(':percent% answered right', ['percent' => $right]) }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-graphic-card>
                @endforeach

                {{-- Mokre szmaty: gracze przegrani z botem --}}
                @php $rags = MatchdaySummary::wetRags($md); @endphp
                <x-graphic-card :title="__('Wet rags')" :when="__('After the match')" background="linear-gradient(135deg, #1f2937 0%, #374151 50%, #0b1f4d 100%)"
                    :text="($rags === [] ? __('Nobody lost to a bot this time. The bots go home empty-handed!') : __('Wet rags of matchday :n: :list. Beaten by a bot!', ['n' => $md->number, 'list' => collect($rags)->pluck('team')->implode(', ')])) . $hashtag"
                    :badge="count($rags) > 6 ? __('The image shows 6 of :n teams.', ['n' => count($rags)]) : ''">
                    <div class="flex h-full flex-col p-4">
                        {!! $top !!}
                        <div class="mt-3 text-lg font-black leading-tight">🧽 {{ __('Wet rags') }}</div>
                        <div class="text-xs text-white/70">{{ __('Lost to a bot') }}</div>
                        <div class="my-auto space-y-1.5">
                            @forelse (array_slice($rags, 0, 6) as $rag)
                                <div class="rounded-lg bg-white/10 px-2 py-1 text-xs">
                                    <div class="flex justify-between gap-2 font-semibold"><span class="truncate">{{ $rag['team'] }}</span><span class="shrink-0">{{ $rag['score'] }}</span></div>
                                    <div class="truncate text-[10px] text-white/60">{{ __('vs :bot', ['bot' => $rag['bot']]) }} · {{ $rag['competition'] }}</div>
                                </div>
                            @empty
                                <div class="text-center text-sm font-bold">{{ __('Nobody lost to a bot this time. The bots go home empty-handed!') }}</div>
                            @endforelse
                        </div>
                    </div>
                </x-graphic-card>

                {{-- Odpadli z Ligi Legend --}}
                @php $out = MatchdaySummary::legendsOut($md); @endphp
                @if ($out !== [])
                    <x-graphic-card :title="__('Out of the Legends League')" :when="__('After the match')"
                        :text="__('Two defeats in one matchday and the adventure is over for: :names.', ['names' => implode(', ', $out)]) . $hashtag"
                        :badge="count($out) > 8 ? __('The image shows 8 of :n teams.', ['n' => count($out)]) : ''">
                        <div class="flex h-full flex-col p-4">
                            {!! $top !!}
                            <div class="mt-3 text-lg font-black leading-tight">{{ __('Out of the Legends League') }}</div>
                            <div class="text-xs text-lech-200">{{ __('Two defeats in one matchday') }}</div>
                            <div class="my-auto space-y-1 text-sm font-semibold">
                                @foreach (array_slice($out, 0, 8) as $name)
                                    <div class="truncate">✗ {{ $name }}</div>
                                @endforeach
                            </div>
                        </div>
                    </x-graphic-card>
                @endif
            </div>
        </section>
    @endif
</div>
