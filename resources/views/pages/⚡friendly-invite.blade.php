<?php

use App\Models\Friendly;
use App\Support\Friendlies;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Link zaproszenia na sparing (/sparing/{token}). Gość widzi, kto go wyzywa, a token zostaje w sesji: po rejestracji
 * albo logowaniu wraca na stronę sparingów, gdzie czeka karta z przyjęciem. Zalogowany gracz przyjmuje od razu.
 */
new #[Layout('layouts::public')] class extends Component {
    #[Locked]
    public int $friendlyId;

    public function mount(string $token): void
    {
        $friendly = Friendly::where('token', $token)->firstOrFail();
        $this->friendlyId = $friendly->id;

        if (! auth()->check()) {
            session()->put('friendly_token', $token);
            session()->put('url.intended', route('friendlies'));
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('Friendly'));
    }

    public function friendly(): Friendly
    {
        return Friendly::with(['challenger', 'opponent', 'matchday'])->findOrFail($this->friendlyId);
    }

    /** Czy zaproszenie wciąż czeka na rywala i przed pierwszym gwizdkiem. */
    public function open(): bool
    {
        $friendly = $this->friendly();

        return $friendly->status === Friendly::PENDING && (bool) $friendly->matchday?->isOpenForTips();
    }

    public function accept(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }
        try {
            Friendlies::accept($this->friendly(), $user);
            session()->forget('friendly_token');
            $this->dispatch('ui-toast', variant: 'success', text: __('Friendly accepted. Set your squad and questions!'));
            $this->redirectRoute('tips', navigate: true);
        } catch (DomainException $e) {
            $this->dispatch('ui-toast', variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

@php
    $friendly = $this->friendly();
    $md = $friendly->matchday;
@endphp

<div class="mx-auto flex w-full max-w-md flex-col gap-3.5 py-6">
    <x-ui.card hero class="space-y-3 text-center">
        <p class="text-xs font-bold uppercase tracking-[.3em] opacity-80">{{ __('Friendly') }}</p>
        <p class="font-display text-2xl font-semibold uppercase">{{ __(':team invites you to a friendly', ['team' => Friendlies::teamName($friendly->challenger)]) }}</p>
        @if ($md)
            <p class="text-sm opacity-90">{{ __('Matchday :number', ['number' => $md->number]) }} · {{ $md->fixture }} · {{ $md->kickoff_at?->translatedFormat('D j M, H:i') }}</p>
        @endif
        <p class="text-sm opacity-90">{{ __('You both tip the Lech match and set a squad. Whoever scores more wins. Only for prestige, no table points.') }}</p>
    </x-ui.card>

    @if (! $this->open())
        <x-ui.card class="text-center text-sm text-ui-muted">{{ __('This friendly is no longer waiting for an answer.') }}</x-ui.card>
    @elseif (auth()->check())
        @if (auth()->id() === $friendly->challenger_id)
            <x-ui.card class="text-center text-sm text-ui-muted">{{ __('This is your own invite. Send the link to a friend.') }}</x-ui.card>
        @else
            <x-ui.button wire:click="accept">{{ __('Accept the friendly') }}</x-ui.button>
        @endif
    @else
        <x-ui.button :href="route('register')">{{ __('Create a free account') }}</x-ui.button>
        <x-ui.button variant="ghost" :href="route('login')">{{ __('Log in') }}</x-ui.button>
    @endif
</div>
