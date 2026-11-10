<?php

use App\Models\OfficeRequest;
use App\Support\Office;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Biuro skarg i wniosków w panelu (App\Support\Office): sprawy czekające na decyzję (odwołania, inne sprawy,
 * wnioski o nazwy botów po udanym głosowaniu), trwające głosowania i archiwum. Akceptacja odwołania zdejmuje rolę
 * Banned/Inactive, akceptacja wniosku nadaje botowi nazwę (można ją poprawić). Odpowiedź widzi autor.
 */
new class extends Component {
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    #[Url(except: '')]
    public string $type = '';

    // Decyzja w oknie
    public ?int $decideId = null;
    public bool $accept = true;
    public string $reply = '';
    public string $botName = '';

    public function render(): View
    {
        return $this->view()->title(__('Complaints and requests office'));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'type'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function stats(): array
    {
        $counts = OfficeRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[OfficeRequest::PENDING] ?? 0),
            'voting' => (int) ($counts[OfficeRequest::VOTING] ?? 0),
            'done' => (int) (($counts[OfficeRequest::ACCEPTED] ?? 0) + ($counts[OfficeRequest::REJECTED] ?? 0) + ($counts[OfficeRequest::VOTE_FAILED] ?? 0)),
        ];
    }

    /** Liczba spraw każdego rodzaju w wybranym stanie ('' = wszystkie rodzaje). */
    #[Computed]
    public function typeCounts(): array
    {
        $counts = OfficeRequest::query()
            ->when($this->status === 'pending', fn ($q) => $q->where('status', OfficeRequest::PENDING))
            ->when($this->status === 'voting', fn ($q) => $q->where('status', OfficeRequest::VOTING))
            ->when($this->status === 'done', fn ($q) => $q->whereNotIn('status', OfficeRequest::OPEN))
            ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return ['' => (int) $counts->sum()] + array_map('intval', array_replace(array_fill_keys(OfficeRequest::TYPES, 0), $counts->all()));
    }

    #[Computed]
    public function requests()
    {
        return OfficeRequest::with(['user:id,name,email,team_name', 'user.roles:id,name', 'bot:id,name,sort_order', 'reviewer:id,name'])
            ->when($this->status === 'pending', fn ($q) => $q->where('status', OfficeRequest::PENDING)->oldest('updated_at'))
            ->when($this->status === 'voting', fn ($q) => $q->where('status', OfficeRequest::VOTING)->latest('id'))
            ->when($this->status === 'done', fn ($q) => $q->whereNotIn('status', OfficeRequest::OPEN)->latest('updated_at'))
            ->when(in_array($this->type, OfficeRequest::TYPES, true), fn ($q) => $q->where('type', $this->type))
            ->paginate(15);
    }

    public function open(int $id, bool $accept): void
    {
        $this->authorizeAdmin();

        $request = OfficeRequest::open()->findOrFail($id);
        $this->decideId = $request->id;
        $this->accept = $accept;
        $this->reply = '';
        $this->botName = (string) $request->bot_name;
        $this->resetValidation();

        $this->dispatch('ui-sheet', name: 'office-decide');
    }

    public function decide(): void
    {
        $this->authorizeAdmin();
        $this->validate(['reply' => ['nullable', 'string', 'max:500'], 'botName' => ['nullable', 'string', 'max:40']]);

        $request = OfficeRequest::find($this->decideId);
        if (! $request) {
            return;
        }

        try {
            Office::decide($request, $this->accept, $this->reply, auth()->user(), $this->botName);
        } catch (DomainException $e) {
            $this->addError('botName', $e->getMessage());

            return;
        }

        $this->dispatch('ui-sheet', name: 'office-decide', open: false);
        $this->reset('decideId', 'reply', 'botName');
        unset($this->stats, $this->typeCounts, $this->requests);
        $this->dispatch('ui-toast', variant: 'success', text: $this->accept ? __('Request accepted.') : __('Request rejected.'));
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole(\App\Enums\RoleName::Admin->value), 403);
    }
}; ?>

@php
    $decided = $decideId ? \App\Models\OfficeRequest::with('user', 'bot')->find($decideId) : null;
    $typeLabels = [
        '' => __('All kinds'),
        \App\Models\OfficeRequest::BAN_APPEAL => __('Ban appeal'),
        \App\Models\OfficeRequest::INACTIVE_APPEAL => __('Inactive status appeal'),
        \App\Models\OfficeRequest::BOT_RENAME => __('Bot rename request'),
        \App\Models\OfficeRequest::OTHER => __('Other complaint or request'),
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <x-ui.heading size="xl">{{ __('Complaints and requests office') }}</x-ui.heading>
            <x-ui.text>{{ __('Appeals go straight to you. Bot names come here after the players\' vote (:votes votes, at least :approval% for).', ['votes' => \App\Support\Office::VOTES_NEEDED, 'approval' => \App\Support\Office::APPROVAL]) }}</x-ui.text>
        </div>
    </div>

    {{-- Filtry jako przyciski: stan sprawy i rodzaj (liczby dla wybranego stanu). --}}
    <div class="space-y-2">
        <x-ui.filter-pills model="status" :value="$status" :label="__('Status')" :counts="$this->stats"
            :options="['pending' => __('Waiting for decision'), 'voting' => __('Players are voting'), 'done' => __('Decided')]" />
        <x-ui.filter-pills model="type" :value="$type" :label="__('Kind')" :counts="$this->typeCounts" :options="$typeLabels" />
    </div>

    <div class="space-y-3">
        @forelse ($this->requests as $request)
            <x-ui.card wire:key="office-admin-{{ $request->id }}" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge size="sm">{{ $request->typeLabel() }}</x-ui.badge>
                        <x-ui.badge size="sm" :color="$request->statusColor()">{{ $request->statusLabel() }}</x-ui.badge>
                        <span class="text-xs text-ui-muted">{{ $request->created_at?->format('d.m.Y H:i') }}</span>
                    </div>
                    @if ($request->user)
                        <span class="text-sm">
                            <x-ui.link :href="route('dashboard.users', ['search' => $request->user->email])" wire:navigate>{{ $request->user->name }}</x-ui.link>
                            <span class="text-ui-muted">· {{ $request->user->team_name }} · {{ $request->user->roles->pluck('name')->join(', ') ?: __('no role') }}</span>
                        </span>
                    @endif
                </div>

                @if ($request->type === \App\Models\OfficeRequest::BOT_RENAME)
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="italic text-ui-muted">#{{ $request->bot?->sort_order }} {{ $request->old_bot_name ?? $request->bot?->name }}</span>
                        <x-ui.icon name="arrow-long-right" class="size-4 text-zinc-400" />
                        <span class="font-semibold italic">{{ $request->bot_name }}</span>
                        <span class="text-xs text-ui-muted">· 👍 {{ $request->votes_up }} · 👎 {{ $request->votes_down }} ({{ $request->approval() }}%)</span>
                    </div>
                @endif

                <p class="whitespace-pre-line text-sm">{{ $request->body }}</p>

                @if ($request->admin_reply || $request->reviewer)
                    <p class="text-xs text-ui-muted">
                        {{ __('Decided by :name on :date', ['name' => $request->reviewer?->name ?? '—', 'date' => $request->reviewed_at?->format('d.m.Y H:i') ?? '—']) }}
                        @if ($request->admin_reply)· „{{ $request->admin_reply }}”@endif
                    </p>
                @endif

                @if (in_array($request->status, \App\Models\OfficeRequest::OPEN, true))
                    <div class="flex gap-2">
                        <x-ui.btn size="sm" variant="primary" icon="check" wire:click="open({{ $request->id }}, true)">{{ __('Accept') }}</x-ui.btn>
                        <x-ui.btn size="sm" variant="danger" icon="x-mark" wire:click="open({{ $request->id }}, false)">{{ __('Reject') }}</x-ui.btn>
                    </div>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.text>{{ __('Nothing here.') }}</x-ui.text></x-ui.card>
        @endforelse
    </div>

    <x-ui.pagination :paginator="$this->requests" />

    <x-ui.modal name="office-decide" class="md:w-[32rem]">
        @if ($decided)
            <form wire:submit="decide" class="space-y-4">
                <x-ui.heading size="lg">{{ $accept ? __('Accept request') : __('Reject request') }}</x-ui.heading>
                <x-ui.text>{{ $decided->typeLabel() }} · {{ $decided->user?->name }}</x-ui.text>
                @if ($accept)
                    <x-ui.text class="text-sm">
                        {{ match ($decided->type) {
                            \App\Models\OfficeRequest::BAN_APPEAL => __('The ban will be lifted and the player returns to the game.'),
                            \App\Models\OfficeRequest::INACTIVE_APPEAL => __('The Inactive status will be lifted and the player returns to Liga podwórkowa.'),
                            \App\Models\OfficeRequest::BOT_RENAME => __('The bot gets the new name.'),
                            default => __('The request is marked as accepted.'),
                        } }}
                    </x-ui.text>
                    @if ($decided->type === \App\Models\OfficeRequest::BOT_RENAME)
                        <x-ui.input wire:model="botName" :label="__('New bot name')" maxlength="40" />
                    @endif
                @endif
                <x-ui.error name="botName" />
                <x-ui.textarea wire:model="reply" :label="__('Reply to the player (optional)')" rows="3" maxlength="500" />
                <div class="flex justify-end gap-2">
                    <x-ui.modal.close><x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn></x-ui.modal.close>
                    <x-ui.btn type="submit" :variant="$accept ? 'primary' : 'danger'">{{ $accept ? __('Accept') : __('Reject') }}</x-ui.btn>
                </div>
            </form>
        @endif
    </x-ui.modal>
</div>
