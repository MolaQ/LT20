<?php

use App\Enums\CompetitionType;
use App\Models\Friendly;
use App\Models\Matchday;
use App\Models\User;
use App\Support\Friendlies;
use App\Support\Players;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Sparingi (27.11.2026): wyzwanie gracza albo link do znajomego, wyzwania czekające na odpowiedź, przyjęty sparing
 * w najbliższej kolejce, bilans i wyniki z przyciskiem „Rewanż”. Zasady w App\Support\Friendlies.
 */
new #[Layout('layouts::shell')] class extends Component {
    /** Szukana drużyna do wyzwania. */
    public string $search = '';

    /** Sparing, którego grafika wyniku jest otwarta (okno „Pochwal się wynikiem”). */
    public ?int $story = null;

    public function render(): View
    {
        return $this->view()->title(__('Friendlies'));
    }

    private function me(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    #[Computed]
    public function matchday(): ?Matchday
    {
        return Friendlies::nextMatchday();
    }

    /** Dlaczego nie mogę teraz umówić sparingu (null: mogę). */
    #[Computed]
    public function blocker(): ?string
    {
        return $this->matchday ? Friendlies::blocker($this->me(), $this->matchday) : __('There is no open matchday for a friendly right now.');
    }

    /** Mój sparing w najbliższej kolejce (czeka albo przyjęty). */
    #[Computed]
    public function active(): ?Friendly
    {
        return $this->matchday ? Friendlies::activeFor($this->me()->id, $this->matchday)?->load(['challenger', 'opponent']) : null;
    }

    /** Typ rozgrywek, w którym zagram sparing (pierwszy wolny). */
    #[Computed]
    public function mySet(): ?string
    {
        return $this->matchday ? (Friendlies::freeSets($this->me()->id, $this->matchday)[0] ?? null) : null;
    }

    /**
     * Wyzwania do mnie czekające na odpowiedź.
     *
     * @return Collection<int, Friendly>
     */
    #[Computed]
    public function incoming(): Collection
    {
        return Friendly::with(['challenger', 'matchday'])->where('opponent_id', $this->me()->id)->where('status', Friendly::PENDING)
            ->latest()->get()->filter(fn (Friendly $f) => $f->matchday?->isOpenForTips())->values();
    }

    /** Zaproszenie z linku, które gość otworzył przed zalogowaniem. */
    #[Computed]
    public function invite(): ?Friendly
    {
        $token = session('friendly_token');
        $friendly = $token ? Friendly::with(['challenger', 'matchday'])->where('token', $token)->first() : null;

        return $friendly && $friendly->status === Friendly::PENDING && $friendly->challenger_id !== $this->me()->id
            && $friendly->opponent_id === null && $friendly->matchday?->isOpenForTips() ? $friendly : null;
    }

    /**
     * Rozegrane sparingi, najnowsze na górze.
     *
     * @return Collection<int, Friendly>
     */
    #[Computed]
    public function played(): Collection
    {
        return Friendly::of($this->me()->id)->with(['challenger', 'opponent', 'matchday'])->whereNotNull('settled_at')->latest('settled_at')->limit(20)->get();
    }

    /**
     * Ranking sparingów bieżącego sezonu (pierwsza dziesiątka).
     *
     * @return list<array{user: User, played: int, won: int, drawn: int, lost: int, for: int, against: int, points: int}>
     */
    #[Computed]
    public function ranking(): array
    {
        $season = \App\Models\Season::current();

        return $season ? Friendlies::ranking($season->id) : [];
    }

    /** @return array{played: int, won: int, drawn: int, lost: int} */
    #[Computed]
    public function record(): array
    {
        return Friendlies::record($this->me()->id);
    }

    /**
     * Drużyny do wyzwania (po nazwie drużyny albo gracza), bez mnie i graczy bez prawa gry.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function results(): Collection
    {
        $term = trim($this->search);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return User::with('roles')->whereKeyNot($this->me()->id)
            ->where(fn ($q) => $q->where('team_name', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%'))
            ->orderBy('team_name')->limit(20)->get()
            ->filter(fn (User $user) => Players::canPlay($user))->take(8)->values();
    }

    /** Wyzwanie gracza (id) albo otwarty link (null). */
    public function challenge(?int $userId = null): void
    {
        if (! $this->matchday) {
            return;
        }
        $opponent = $userId ? User::find($userId) : null;
        try {
            Friendlies::challenge($this->me(), $this->matchday, $opponent);
            $this->search = '';
            $this->dispatch('ui-toast', variant: 'success', text: $opponent ? __('Challenge sent to :team.', ['team' => Friendlies::teamName($opponent)]) : __('Link ready. Send it to a friend!'));
        } catch (DomainException $e) {
            $this->dispatch('ui-toast', variant: 'danger', text: $e->getMessage());
        }
        $this->refresh();
    }

    /** Rewanż: nowe wyzwanie tego samego rywala w najbliższej kolejce. */
    public function rematch(int $friendlyId): void
    {
        $friendly = Friendly::of($this->me()->id)->find($friendlyId);
        $rival = $friendly?->rivalOf($this->me()->id);
        if ($rival) {
            $this->challenge($rival->id);
        }
    }

    /** Grafika wyniku rozegranego sparingu w oknie. */
    public function showStory(int $friendlyId): void
    {
        $this->story = Friendly::of($this->me()->id)->whereNotNull('settled_at')->whereKey($friendlyId)->exists() ? $friendlyId : null;
        if ($this->story) {
            $this->dispatch('ui-sheet', name: 'friendly-story');
        }
    }

    #[Computed]
    public function storyFriendly(): ?Friendly
    {
        return $this->story ? $this->played->firstWhere('id', $this->story) : null;
    }

    public function accept(int $friendlyId): void
    {
        $friendly = Friendly::find($friendlyId);
        if (! $friendly) {
            return;
        }
        try {
            Friendlies::accept($friendly, $this->me());
            session()->forget('friendly_token');
            $this->dispatch('ui-toast', variant: 'success', text: __('Friendly accepted. Set your squad and questions!'));
        } catch (DomainException $e) {
            $this->dispatch('ui-toast', variant: 'danger', text: $e->getMessage());
        }
        $this->refresh();
    }

    public function decline(int $friendlyId): void
    {
        if ($friendly = Friendly::find($friendlyId)) {
            Friendlies::decline($friendly, $this->me());
        }
        $this->refresh();
    }

    public function dismissInvite(): void
    {
        session()->forget('friendly_token');
        $this->refresh();
    }

    public function cancel(int $friendlyId): void
    {
        if ($friendly = Friendly::find($friendlyId)) {
            Friendlies::cancel($friendly, $this->me());
        }
        $this->refresh();
    }

    private function refresh(): void
    {
        unset($this->active, $this->blocker, $this->incoming, $this->invite, $this->mySet, $this->results);
    }
}; ?>

@php
    $me = auth()->user();
    $record = $this->record;
    $md = $this->matchday;
    $active = $this->active;
    $setLabel = fn (?string $set) => $set ? CompetitionType::from($set)->label() : '';
@endphp

<div class="flex flex-col gap-3.5">
    <div class="flex items-center justify-between gap-2">
        <h1 class="font-display text-[28px] font-semibold uppercase tracking-tight">{{ __('Friendlies') }}</h1>
        <x-ui.chip>{{ $record['won'] }}–{{ $record['drawn'] }}–{{ $record['lost'] }}</x-ui.chip>
    </div>
    <p class="text-sm text-ui-muted">{{ __('Challenge another team or a friend to a friendly in the next matchday. It is scored like a league duel (tip, players and questions), only for prestige: no table or Hall of Fame points.') }}</p>

    {{-- Zaproszenie z linku otwarte przed zalogowaniem --}}
    @if ($invite = $this->invite)
        <x-ui.card hero class="space-y-3">
            <p class="font-display text-xl font-semibold uppercase">{{ __(':team invites you to a friendly', ['team' => Friendlies::teamName($invite->challenger)]) }}</p>
            <p class="text-sm opacity-90">{{ __('Matchday :number', ['number' => $invite->matchday->number]) }} · {{ $invite->matchday->kickoff_at?->translatedFormat('D j M, H:i') }}</p>
            <div class="flex gap-2">
                <x-ui.button variant="light" wire:click="accept({{ $invite->id }})">{{ __('Accept') }}</x-ui.button>
                <x-ui.button variant="ghost" wire:click="dismissInvite">{{ __('Not now') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    {{-- Wyzwania do mnie --}}
    @foreach ($this->incoming as $friendly)
        <x-ui.card class="space-y-3">
            <p class="font-semibold">{{ __(':team challenges you to a friendly', ['team' => Friendlies::teamName($friendly->challenger)]) }}</p>
            <p class="text-sm text-ui-muted">{{ __('Matchday :number', ['number' => $friendly->matchday->number]) }} · {{ $friendly->matchday->kickoff_at?->translatedFormat('D j M, H:i') }}</p>
            <div class="flex gap-2">
                <x-ui.button size="sm" wire:click="accept({{ $friendly->id }})">{{ __('Accept') }}</x-ui.button>
                <x-ui.button size="sm" variant="ghost" wire:click="decline({{ $friendly->id }})">{{ __('Decline') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endforeach

    {{-- Najbliższa kolejka: mój sparing albo wyzwanie --}}
    <x-ui.section-title>{{ $md ? __('Matchday :number', ['number' => $md->number]).' · '.$md->fixture : __('Next matchday') }}</x-ui.section-title>

    @if ($active && $active->status === Friendly::ACCEPTED)
        <x-ui.card class="space-y-3">
            <p class="font-display text-xl font-semibold uppercase">{{ Friendlies::label($active, $me->id) }}</p>
            <p class="text-sm text-ui-muted">{{ __('You play it in the :set slot: tip, squad and questions of this type count.', ['set' => $setLabel($active->setFor($me->id))]) }}</p>
            <x-ui.button size="sm" :href="route('tips')">{{ __('Set squad and questions') }}</x-ui.button>
        </x-ui.card>
    @elseif ($active)
        @php $link = route('friendly.invite', ['token' => $active->token, 'z' => $me->id]); @endphp
        <x-ui.card class="space-y-3">
            @if ($active->opponent)
                <p class="font-semibold">{{ __('Waiting for :team to answer.', ['team' => Friendlies::teamName($active->opponent)]) }}</p>
            @else
                <p class="font-semibold">{{ __('Your invite link is ready. The first player to accept it plays you.') }}</p>
                <div x-data="{ copied: false }" class="flex flex-col gap-2 sm:flex-row">
                    <input readonly value="{{ $link }}" class="h-10 w-full rounded-xl border border-ui-line bg-ui-raised px-3 text-sm" @focus="$el.select()">
                    <x-ui.button size="sm" variant="ghost" class="sm:w-auto" x-on:click="navigator.clipboard.writeText(@js($link)); copied = true">
                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy link'))">{{ __('Copy link') }}</span>
                    </x-ui.button>
                    <a href="https://wa.me/?text={{ rawurlencode(__('Play a friendly with me in LechTYPER, matchday :number:', ['number' => $md?->number]).' '.$link) }}" target="_blank" rel="noopener"
                        class="inline-flex items-center justify-center rounded-2xl bg-[#25d366] px-3 py-2 text-sm font-semibold text-white">WhatsApp</a>
                </div>
            @endif
            <x-ui.button size="sm" variant="ghost" wire:click="cancel({{ $active->id }})">{{ __('Withdraw the challenge') }}</x-ui.button>
        </x-ui.card>
    @elseif ($this->blocker)
        <x-ui.card class="text-sm text-ui-muted">{{ $this->blocker }}</x-ui.card>
    @else
        <x-ui.card class="space-y-3">
            <p class="text-sm">{{ __('You would play it in the :set slot (the one you have free in this matchday).', ['set' => $setLabel($this->mySet)]) }}</p>
            <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Find a team to challenge')" />
            @foreach ($this->results as $user)
                <div class="flex items-center justify-between gap-2" wire:key="u-{{ $user->id }}">
                    <span class="min-w-0 truncate font-semibold">{{ Friendlies::teamName($user) }}</span>
                    <x-ui.button size="sm" class="w-auto" wire:click="challenge({{ $user->id }})">{{ __('Challenge') }}</x-ui.button>
                </div>
            @endforeach
            <x-ui.separator />
            <x-ui.button size="sm" variant="ghost" wire:click="challenge">{{ __('Create a link for a friend') }}</x-ui.button>
        </x-ui.card>
    @endif

    {{-- Ranking sezonu: 3 pkt za wygraną, 1 za remis --}}
    @if ($this->ranking !== [])
        <x-ui.section-title>{{ __('Friendlies ranking') }}</x-ui.section-title>
        <x-ui.card class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase tracking-wider text-ui-muted">
                    <tr>
                        <th class="py-1.5 pe-2 text-start">#</th>
                        <th class="py-1.5 pe-2 text-start">{{ __('Team') }}</th>
                        <th class="px-1.5 py-1.5 text-end">{{ __('Pl') }}</th>
                        <th class="px-1.5 py-1.5 text-end">{{ __('W–D–L') }}</th>
                        <th class="px-1.5 py-1.5 text-end">{{ __('Goals') }}</th>
                        <th class="ps-1.5 py-1.5 text-end">{{ __('Pts') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ui-line">
                    @foreach ($this->ranking as $i => $row)
                        <tr wire:key="rank-{{ $row['user']->id }}" @class(['font-bold text-club' => $row['user']->id === $me->id])>
                            <td class="py-2 pe-2 tabular-nums">{{ $i + 1 }}.</td>
                            <td class="max-w-[10rem] truncate py-2 pe-2">
                                <a href="{{ route('team.show', $row['user']) }}" wire:navigate class="hover:underline">{{ Friendlies::teamName($row['user']) }}</a>
                            </td>
                            <td class="px-1.5 py-2 text-end tabular-nums">{{ $row['played'] }}</td>
                            <td class="whitespace-nowrap px-1.5 py-2 text-end tabular-nums">{{ $row['won'] }}–{{ $row['drawn'] }}–{{ $row['lost'] }}</td>
                            <td class="whitespace-nowrap px-1.5 py-2 text-end tabular-nums">{{ $row['for'] }}:{{ $row['against'] }}</td>
                            <td class="ps-1.5 py-2 text-end font-bold tabular-nums">{{ $row['points'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif

    {{-- Wyniki i rewanże --}}
    @if ($this->played->isNotEmpty())
        <x-ui.section-title>{{ __('Results') }}</x-ui.section-title>
        <x-ui.card class="divide-y divide-ui-line">
            @foreach ($this->played as $friendly)
                @php
                    [$mine, $theirs] = $friendly->scoreFor($me->id) ?? [0, 0];
                    $rival = $friendly->rivalOf($me->id);
                @endphp
                <div class="flex items-center gap-3 py-2.5" wire:key="p-{{ $friendly->id }}">
                    <x-ui.chip :variant="$mine > $theirs ? 'good' : ($mine < $theirs ? 'bad' : 'default')">{{ $mine }}:{{ $theirs }}</x-ui.chip>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ Friendlies::teamName($rival) }}</p>
                        <p class="text-xs text-ui-muted">{{ __('Matchday :number', ['number' => $friendly->matchday?->number]) }}</p>
                    </div>
                    <button type="button" wire:click="showStory({{ $friendly->id }})" class="grid size-9 place-items-center rounded-xl text-ui-muted hover:bg-ui-raised hover:text-ui-ink" aria-label="{{ __('Show off the result') }}">
                        <x-ui.icon name="share" variant="mini" class="size-5" />
                    </button>
                    @if ($rival && ! $this->blocker && ! $active)
                        <x-ui.button size="sm" variant="ghost" class="w-auto" wire:click="rematch({{ $friendly->id }})">{{ __('Rematch') }}</x-ui.button>
                    @endif
                </div>
            @endforeach
        </x-ui.card>
    @endif

    {{-- Grafika wyniku sparingu do relacji --}}
    <x-ui.modal name="friendly-story" class="w-full md:w-[26rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('Show off the result') }}</x-ui.heading>
            <x-ui.text>{{ __('Tap the share icon on the graphic to post it in a story or send it to friends.') }}</x-ui.text>
            @if ($storyFriendly = $this->storyFriendly)
                <x-friendly-story :friendly="$storyFriendly" :user-id="$me->id" wire:key="fstory-{{ $storyFriendly->id }}" />
            @endif
        </div>
    </x-ui.modal>
</div>
