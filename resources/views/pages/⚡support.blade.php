<?php

use App\Models\Payment;
use App\Support\Supporters;
use App\Support\Premium;
use App\Support\Przelewy24;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Premium i wsparcie (etap 15, regulamin, punkty 10 i 11): cennik premium, cegiełka dowolną kwotą, płatność
 * przez Przelewy24, stan premium gracza i historia wpłat. Typowanie jest zawsze darmowe.
 * Po powrocie z P24 adres ma ?payment=… i pokazujemy stan tej wpłaty (potwierdzenie przychodzi osobno).
 */
new #[Layout('layouts::public')] class extends Component {
    /** Wpłata, z której gracz wrócił z Przelewy24. */
    #[Url(as: 'payment', except: '')]
    public string $returned = '';

    /** Cegiełka w złotych. */
    public string $supportAmount = '20';

    public function render(): View
    {
        return $this->view()->title(__('Premium and support'));
    }

    #[Computed]
    public function configured(): bool
    {
        return Przelewy24::configured();
    }

    /** Premium za darmo dla wszystkich: bez cennika i cegiełki, tylko dobrowolna kawka. */
    #[Computed]
    public function free(): bool
    {
        return Premium::free();
    }

    /** Profil na buycoffee.to: kawka to zawsze tylko dobrowolne wsparcie, nie daje premium (13.11.2026). */
    #[Computed]
    public function coffeeUrl(): ?string
    {
        return Payment::buyCoffeeUrl();
    }

    /** Cennik i cegiełka: tylko gdy premium nie jest za darmo i płatności Przelewy24 są ustawione. */
    #[Computed]
    public function sales(): bool
    {
        return !$this->free && $this->configured;
    }

    #[Computed]
    public function isPremium(): bool
    {
        return Premium::isActive(auth()->user());
    }

    #[Computed]
    public function returnedPayment(): ?Payment
    {
        return $this->returned === '' || !auth()->check()
            ? null
            : Payment::where('session_id', $this->returned)->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function payments()
    {
        return auth()->check() ? Payment::where('user_id', auth()->id())->latest()->limit(10)->get() : collect();
    }

    /** Suma wpłat gracza z 12 miesięcy i miejsce w rankingu wspierających. @return array{total: int, rank: ?int} */
    #[Computed]
    public function supporter(): array
    {
        $ranking = Supporters::ranking()->values();
        $index = $ranking->search(fn($row) => $row['user_id'] === auth()->id());

        return [
            'total' => $index === false ? 0 : $ranking[$index]['total'],
            'rank' => $index === false ? null : $index + 1,
        ];
    }

    /** Premium według cennika. */
    public function buy(string $plan): void
    {
        abort_unless(isset(Premium::PLANS[$plan]), 422);
        [$amount, $days] = Premium::PLANS[$plan];

        $this->startPayment(Payment::KIND_PREMIUM, $plan, $amount, $days, __('Premium: :plan', ['plan' => Premium::labels()[$plan]]));
    }

    /** Cegiełka: premium według najwyższej opcji cennika, na którą wystarcza kwoty. */
    public function support(): void
    {
        $this->validate(
            ['supportAmount' => ['required', 'numeric', 'min:' . Premium::MIN_SUPPORT / 100, 'max:' . Premium::MAX_SUPPORT / 100]],
            [],
            ['supportAmount' => __('Amount')],
        );

        $amount = (int) round((float) str_replace(',', '.', $this->supportAmount) * 100);

        $this->startPayment(Payment::KIND_SUPPORT, null, $amount, Premium::daysFor($amount), __('Support for LechTyper'));
    }

    private function startPayment(string $kind, ?string $plan, int $amount, int $days, string $description): void
    {
        abort_unless($this->sales, 404);
        $user = auth()->user();

        if (!$user) {
            $this->redirectRoute('login');

            return;
        }

        if (!$this->configured) {
            $this->dispatch('ui-toast', variant: 'warning', text: __('Online payments are not available yet.'));

            return;
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'plan' => $plan,
            'amount' => $amount,
            'status' => Payment::PENDING,
            'source' => 'p24',
            'session_id' => 'LT-' . Str::uuid(),
            'premium_days' => $days,
        ]);

        try {
            $url = app(Przelewy24::class)->register($payment, $user, $description);
        } catch (\Throwable $e) {
            $payment->update(['status' => Payment::FAILED, 'note' => Str::limit($e->getMessage(), 250)]);
            report($e);
            $this->dispatch('ui-toast', variant: 'danger', text: __('The payment could not be started. Try again later.'));

            return;
        }

        $this->redirect($url);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-banner :title="$this->free ? __('Support LechTYPER') : __('Premium and support')"
        :subtitle="$this->free ? __('Tipping and premium are free for everyone. A coffee supports the development of LechTYPER.') : __('Tipping is always free. Premium gives extras and supports the development of LechTYPER.')" />

    {{-- Powrót z Przelewy24 --}}
    @if ($this->returnedPayment)
        @php
            $returnedPayment = $this->returnedPayment;
        @endphp
        {{-- Potwierdzenie z P24 przychodzi osobno, więc dopóki wpłata czeka, odświeżamy stan. --}}
        <div @if ($returnedPayment->status === 'pending') wire:poll.5s @endif>
            <x-ui.callout :variant="$returnedPayment->status === 'paid' ? 'success' : ($returnedPayment->status === 'failed' ? 'danger' : 'warning')"
                :icon="$returnedPayment->status === 'paid' ? 'check-circle' : 'clock'">
                <p class="font-semibold">
                    {{ $returnedPayment->status === 'paid' ? __('Thank you! The payment is confirmed.') : ($returnedPayment->status === 'failed' ? __('The payment failed.') : __('Waiting for the payment confirmation...')) }}
                </p>
                <p>{{ $returnedPayment->kindLabel() }}: {{ $returnedPayment->amountLabel() }}</p>
            </x-ui.callout>
        </div>
    @endif

    @if ($this->coffeeUrl)
        {{-- Kawka: dobrowolne wsparcie, nie daje premium. Admin wpisuje ją w panelu (ranking wspierających) --}}
        <x-ui.card class="space-y-3">
            <x-ui.heading size="lg">{{ __('Buy us a coffee') }}</x-ui.heading>
            <x-ui.text class="text-sm">{{ $this->free ? __('A coffee is a voluntary support for the creator. It changes nothing in the game: premium is free for everyone.') : __('A coffee is a voluntary support for the creator. It does not give premium.') }}</x-ui.text>
            <x-ui.text class="text-sm">
                @auth
                    {{ __('To appear in the supporters ranking, write the e-mail of your LechTYPER account in the message: :email.', ['email' => auth()->user()->email]) }}
                @else
                    {{ __('To appear in the supporters ranking, write the e-mail of your LechTYPER account in the message.') }}
                @endauth
            </x-ui.text>
            <div>
                <x-ui.btn variant="primary" icon="heart" :href="$this->coffeeUrl" target="_blank" rel="noopener">{{ __('Go to buycoffee.to') }}</x-ui.btn>
            </div>
        </x-ui.card>
    @elseif (!$this->configured && !$this->free)
        <x-ui.callout variant="warning" icon="exclamation-triangle">
            <p>{{ __('Online payments are not available yet.') }}</p>
        </x-ui.callout>
    @endif

    {{-- Stan premium gracza --}}
    @auth
        <x-ui.card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-ui.heading size="lg">{{ __('Your premium') }}</x-ui.heading>
                @if ($this->free)
                    <x-ui.badge color="purple" icon="sparkles">{{ __('Free for everyone') }}</x-ui.badge>
                @elseif ($this->isPremium)
                    <x-ui.badge color="purple" icon="sparkles">
                        {{ auth()->user()->premium_until ? __('Active until :date', ['date' => auth()->user()->premium_until->format('d.m.Y H:i')]) : __('Active without an end date') }}
                    </x-ui.badge>
                @else
                    <x-ui.badge color="zinc">{{ __('Inactive') }}</x-ui.badge>
                @endif
            </div>


            <x-ui.text class="text-sm">
                {{ __('Your payments in the last 12 months: :amount.', ['amount' => \App\Models\Payment::money($this->supporter['total'])]) }}
                @if ($this->supporter['rank'])
                    {{ __('Supporters ranking: :rank. place.', ['rank' => $this->supporter['rank']]) }}
                @endif
            </x-ui.text>
        </x-ui.card>
    @endauth

    {{-- Cennik premium i cegiełka: tylko przy płatnościach Przelewy24 (nie przy premium dla wszystkich) --}}
    @if ($this->sales)
    <div class="space-y-3">
        <x-ui.heading size="lg">{{ __('Premium') }}</x-ui.heading>
        <x-ui.text class="text-sm">
            {{ __('Premium: statistics from the other players’ tips while you tip, a rival preview, a team name change without approval and a badge. A new payment extends it.') }}
        </x-ui.text>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach (\App\Support\Premium::PLANS as $plan => [$price, $days])
                <x-ui.card class="flex flex-col items-center gap-2 text-center" wire:key="plan-{{ $plan }}">
                    <x-ui.heading>{{ \App\Support\Premium::labels()[$plan] }}</x-ui.heading>
                    <p class="text-3xl font-black tabular-nums">{{ \App\Models\Payment::money($price) }}</p>
                    <x-ui.text class="text-sm">{{ trans_choice(':count day|:count days', $days, ['count' => $days]) }}</x-ui.text>
                    @if (auth()->check())
                        <x-ui.btn variant="primary" class="w-full" wire:click="buy('{{ $plan }}')" :disabled="!$this->configured">
                            {{ __('Buy') }}
                        </x-ui.btn>
                    @else
                        <x-ui.btn class="w-full" :href="route('login')">{{ __('Log in') }}</x-ui.btn>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    </div>

    {{-- Cegiełka --}}
    <x-ui.card class="space-y-3">
        <x-ui.heading size="lg">{{ __('Support') }}</x-ui.heading>
        <x-ui.text class="text-sm">
            {{ __('Any amount from 5 zł. You get premium for the highest price list option the amount covers.') }}
        </x-ui.text>

        @if (auth()->check())
            <div class="flex flex-wrap items-end gap-2">
                <div class="w-40">
                    <x-ui.input type="number" min="5" step="1" wire:model="supportAmount" :label="__('Amount')" suffix="zł" />
                </div>
                <x-ui.btn variant="primary" icon="heart" wire:click="support" :disabled="!$this->configured">
                    {{ __('Support') }}
                </x-ui.btn>
            </div>
            <x-ui.error name="supportAmount" />
        @else
            <div>
                <x-ui.btn :href="route('login')">{{ __('Log in') }}</x-ui.btn>
            </div>
        @endif
    </x-ui.card>
    @endif

    {{-- Historia wpłat --}}
    @if ($this->payments->isNotEmpty())
        <x-ui.card class="space-y-3">
            <x-ui.heading size="lg">{{ __('Your payments') }}</x-ui.heading>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @foreach ($this->payments as $payment)
                    <div class="flex items-center justify-between gap-3 py-2 text-sm" wire:key="pay-{{ $payment->id }}">
                        <span class="w-32 text-ui-muted">{{ ($payment->paid_at ?? $payment->created_at)->format('d.m.Y H:i') }}</span>
                        <span class="min-w-0 flex-1">{{ $payment->kindLabel() }}</span>
                        <span class="tabular-nums">{{ $payment->amountLabel() }}</span>
                        <x-ui.badge size="sm" :color="$payment->statusColor()">{{ $payment->statusLabel() }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</div>
