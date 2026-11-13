<?php

use App\Actions\Payments\CompletePayment;
use App\Models\Payment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Supporters;
use App\Support\Premium;
use App\Support\Przelewy24;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Wpłaty i premium (etap 15): lista wpłat (Przelewy24 i ręczne), dopisanie wpłaty (np. przelew na konto),
 * zaksięgowanie wiszącej wpłaty, ręczne nadanie albo odebranie premium oraz podgląd rankingu wspierających
 * (suma wpłat z 12 miesięcy). Tylko Admin.
 */
new class extends Component {
    use WithPagination;

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    // Wybór gracza w oknach (wpłata, premium).
    public string $userSearch = '';
    public ?int $userId = null;

    // Nowa wpłata.
    public string $kind = Payment::KIND_SUPPORT;
    public string $plan = 'month';
    public string $amount = '';
    public string $paidAt = '';
    public string $note = '';

    /** Źródło wpłaty wpisywanej w panelu (Payment::MANUAL_SOURCES). */
    public string $source = 'manual';

    // Premium ręcznie.
    public string $premiumUntil = '';

    // Księgowanie wiszącej wpłaty.
    public ?int $completeId = null;

    public function render(): View
    {
        return $this->view()->title(__('Payments'));
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function payments()
    {
        $term = trim($this->search);

        return Payment::with('user:id,name,email,team_name', 'creator:id,name')
            ->when($this->status !== '', fn($q) => $q->where('status', $this->status))
            ->when($term !== '', fn($q) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('team_name', 'like', "%{$term}%")))
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function premiumUsers()
    {
        return User::where('premium_until', '>', now())->orderBy('premium_until')->get(['id', 'name', 'email', 'team_name', 'premium_until']);
    }

    /** Ranking wspierających na dziś: 15 pierwszych (suma wpłat z 12 miesięcy). */
    #[Computed]
    public function supporters()
    {
        $ranking = Supporters::ranking()->take(15);
        $users = User::whereIn('id', $ranking->pluck('user_id'))->get(['id', 'name', 'team_name'])->keyBy('id');

        return $ranking->map(fn($row) => $row + ['user' => $users->get($row['user_id'])]);
    }

    #[Computed]
    public function userResults()
    {
        $term = trim($this->userSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return User::where(fn($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('team_name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'team_name', 'premium_until']);
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    public function pickUser(int $id): void
    {
        $this->userId = $id;
        $this->userSearch = '';
        unset($this->selectedUser, $this->userResults);

        $this->premiumUntil = Premium::isActive($this->selectedUser) && $this->selectedUser->premium_until ? $this->selectedUser->premium_until->format('Y-m-d') : now()->addDays(30)->format('Y-m-d');
    }

    /* ==================================================================
     | WPŁATY
     * ================================================================*/

    public function openPayment(): void
    {
        $this->reset('userSearch', 'userId', 'amount', 'note', 'source');
        $this->kind = Payment::KIND_SUPPORT;
        $this->plan = 'month';
        $this->paidAt = now()->format('Y-m-d');
        $this->resetValidation();

        $this->dispatch('ui-sheet', name: 'payment-form');
    }

    public function updatedPlan(): void
    {
        $this->amount = isset(Premium::PLANS[$this->plan]) ? (string) (Premium::PLANS[$this->plan][0] / 100) : $this->amount;
    }

    public function updatedKind(): void
    {
        if ($this->kind === Payment::KIND_PREMIUM) {
            $this->updatedPlan();
        }
    }

    /** Wpłata spoza Przelewy24 (np. przelew na konto): od razu opłacona, przedłuża premium jak wpłata online. */
    public function savePayment(CompletePayment $complete): void
    {
        $this->validate([
            'userId' => ['required', 'exists:users,id'],
            'kind' => ['required', 'in:' . Payment::KIND_PREMIUM . ',' . Payment::KIND_SUPPORT],
            'plan' => ['required_if:kind,' . Payment::KIND_PREMIUM, 'nullable', 'in:' . implode(',', array_keys(Premium::PLANS))],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'paidAt' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'in:' . implode(',', Payment::MANUAL_SOURCES)],
        ], [], [
            'userId' => __('Player'),
            'amount' => __('Amount'),
            'paidAt' => __('Payment date'),
        ]);

        $amount = (int) round((float) str_replace(',', '.', $this->amount) * 100);
        $isPremium = $this->kind === Payment::KIND_PREMIUM;

        $payment = Payment::create([
            'user_id' => $this->userId,
            'kind' => $this->kind,
            'plan' => $isPremium ? $this->plan : null,
            'amount' => $amount,
            'status' => Payment::PENDING,
            'source' => $this->source,
            // Kawka z buycoffee.to to tylko dobrowolne wsparcie (13.11.2026) i nigdy nie nalicza premium,
            // tak samo każda wpłata przy premium dla wszystkich.
            'premium_days' => Premium::free() || $this->source === 'buycoffee' ? 0 : ($isPremium ? Premium::PLANS[$this->plan][1] : Premium::daysFor($amount)),
            // Dzisiejsza data z bieżącą godziną, wcześniejsza na koniec dnia (kolejność w rankingu wspierających).
            'paid_at' => Carbon::parse($this->paidAt)->isToday() ? now() : Carbon::parse($this->paidAt)->endOfDay(),
            'note' => $this->note ?: null,
            'created_by' => auth()->id(),
        ]);

        $complete->handle($payment);

        $this->dispatch('ui-sheet', name: 'payment-form', open: false);
        $this->clearCaches();
        $this->dispatch('ui-toast', variant: 'success', text: __('Payment saved.'));
    }

    /** Wisząca wpłata online (np. powiadomienie z P24 nie dotarło, a pieniądze są na koncie). */
    public function confirmComplete(int $id): void
    {
        $this->completeId = Payment::where('status', '!=', Payment::PAID)->findOrFail($id)->id;

        $this->dispatch('ui-sheet', name: 'confirm-complete');
    }

    public function complete(CompletePayment $complete): void
    {
        $payment = Payment::where('status', '!=', Payment::PAID)->findOrFail((int) $this->completeId);
        $payment->update(['note' => trim(($payment->note ? $payment->note . ' ' : '') . __('Confirmed by :name', ['name' => auth()->user()->name]))]);

        $complete->handle($payment);

        $this->dispatch('ui-sheet', name: 'confirm-complete', open: false);
        $this->reset('completeId');
        $this->clearCaches();
        $this->dispatch('ui-toast', variant: 'success', text: __('Payment confirmed.'));
    }

    /* ==================================================================
     | PREMIUM RĘCZNIE
     * ================================================================*/

    public function openPremium(?int $userId = null): void
    {
        $this->reset('userSearch', 'userId', 'premiumUntil');
        $this->resetValidation();

        if ($userId) {
            $this->pickUser($userId);
        }

        $this->dispatch('ui-sheet', name: 'premium-form');
    }

    /** Ustawia datę końca premium (dzień włącznie). Data w przeszłości odbiera premium. */
    public function savePremium(): void
    {
        $this->validate(
            ['userId' => ['required', 'exists:users,id'], 'premiumUntil' => ['required', 'date']],
            [],
            ['userId' => __('Player'), 'premiumUntil' => __('Premium until')],
        );

        $user = User::findOrFail($this->userId);
        $old = $user->premium_until?->toDateTimeString();
        $until = Carbon::parse($this->premiumUntil)->endOfDay();

        Premium::setUntil($user, $until->isFuture() ? $until : now());

        Audit::log('premium.manual', $user, ['premium_until' => $old], ['premium_until' => $user->premium_until?->toDateTimeString()]);

        $this->dispatch('ui-sheet', name: 'premium-form', open: false);
        $this->clearCaches();
        $this->dispatch('ui-toast', variant: 'success', text: __('Premium saved.'));
    }

    public ?int $revokeId = null;

    public function confirmRevoke(int $userId): void
    {
        $this->revokeId = User::findOrFail($userId)->id;

        $this->dispatch('ui-sheet', name: 'confirm-revoke');
    }

    public function revokePremium(): void
    {
        $user = User::findOrFail((int) $this->revokeId);
        $old = $user->premium_until?->toDateTimeString();

        Premium::setUntil($user, now());
        Audit::log('premium.manual', $user, ['premium_until' => $old], ['premium_until' => null]);

        $this->dispatch('ui-sheet', name: 'confirm-revoke', open: false);
        $this->reset('revokeId');
        $this->clearCaches();
        $this->dispatch('ui-toast', text: __('Premium revoked.'));
    }

    private function clearCaches(): void
    {
        unset($this->payments, $this->premiumUsers, $this->supporters, $this->selectedUser, $this->userResults);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Payments') }}</x-ui.heading>
            <x-ui.subheading>
                {{ __('Premium and support payments. Online payments come from Przelewy24, others you add by hand.') }}
            </x-ui.subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-ui.btn icon="sparkles" wire:click="openPremium">{{ __('Grant premium') }}</x-ui.btn>
            <x-ui.btn variant="primary" icon="plus" wire:click="openPayment">{{ __('Add payment') }}</x-ui.btn>
        </div>
    </div>

    @if (!\App\Support\Przelewy24::configured())
        <x-ui.callout variant="warning" icon="exclamation-triangle">
            <p>
                {{ __('Przelewy24 is not configured: set P24_MERCHANT_ID, P24_POS_ID, P24_CRC and P24_API_KEY in .env.') }}
            </p>
        </x-ui.callout>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        {{-- Lista wpłat --}}
        <div class="space-y-3">
            <div class="flex flex-wrap gap-2">
                <div class="w-full sm:w-64">
                    <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable
                        :placeholder="__('Search by player...')" />
                </div>
                <x-ui.filter-pills model="status" :value="$status" :label="__('Status')" class="w-full"
                    :options="['' => __('All statuses'), 'paid' => __('Paid'), 'pending' => __('Pending'), 'failed' => __('Failed')]" />
            </div>

            <x-ui.table :paginate="$this->payments">
                <x-ui.table.columns>
                    <x-ui.table.column>{{ __('Date') }}</x-ui.table.column>
                    <x-ui.table.column>{{ __('Player') }}</x-ui.table.column>
                    <x-ui.table.column>{{ __('Type') }}</x-ui.table.column>
                    <x-ui.table.column align="end">{{ __('Amount') }}</x-ui.table.column>
                    <x-ui.table.column>{{ __('Status') }}</x-ui.table.column>
                    <x-ui.table.column></x-ui.table.column>
                </x-ui.table.columns>

                <x-ui.table.rows>
                    @forelse ($this->payments as $payment)
                        <x-ui.table.row :key="$payment->id">
                            <x-ui.table.cell class="whitespace-nowrap text-sm">
                                {{ ($payment->paid_at ?? $payment->created_at)->format('d.m.Y H:i') }}
                            </x-ui.table.cell>
                            <x-ui.table.cell>
                                <div class="text-sm font-medium">{{ $payment->user?->name ?? '—' }}</div>
                                <div class="text-xs text-ui-muted">{{ $payment->user?->email }}</div>
                            </x-ui.table.cell>
                            <x-ui.table.cell class="text-sm">
                                {{ $payment->kindLabel() }}
                                @if ($payment->plan)
                                    ({{ \App\Support\Premium::labels()[$payment->plan] ?? $payment->plan }})
                                @endif
                                <div class="text-xs text-ui-muted">
                                    {{ $payment->sourceLabel() }}{{ $payment->source !== 'p24' && $payment->creator ? ': ' . $payment->creator->name : '' }}
                                    @if ($payment->premium_days > 0)
                                        &middot; {{ trans_choice(':count day|:count days', $payment->premium_days, ['count' => $payment->premium_days]) }}
                                    @endif
                                </div>
                                @if ($payment->note)
                                    <div class="text-xs text-zinc-400">{{ $payment->note }}</div>
                                @endif
                            </x-ui.table.cell>
                            <x-ui.table.cell align="end" class="tabular-nums">{{ $payment->amountLabel() }}</x-ui.table.cell>
                            <x-ui.table.cell>
                                <x-ui.badge size="sm" :color="$payment->statusColor()">{{ $payment->statusLabel() }}</x-ui.badge>
                            </x-ui.table.cell>
                            <x-ui.table.cell align="end">
                                @if ($payment->status !== 'paid')
                                    <x-ui.btn size="xs" icon="check" wire:click="confirmComplete({{ $payment->id }})">
                                        {{ __('Confirm') }}
                                    </x-ui.btn>
                                @endif
                            </x-ui.table.cell>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.row>
                            <x-ui.table.cell colspan="6">{{ __('No payments yet.') }}</x-ui.table.cell>
                        </x-ui.table.row>
                    @endforelse
                </x-ui.table.rows>
            </x-ui.table>
        </div>

        <div class="space-y-6">
            {{-- Premium --}}
            <x-ui.card class="space-y-3">
                <x-ui.heading>{{ __('Active premium') }} ({{ $this->premiumUsers->count() }})</x-ui.heading>
                @forelse ($this->premiumUsers as $user)
                    <div class="flex items-center gap-2 text-sm" wire:key="premium-{{ $user->id }}">
                        <span class="min-w-0 flex-1 truncate">{{ $user->team_name ?: $user->name }}</span>
                        <span class="text-xs text-ui-muted">{{ $user->premium_until->format('d.m.Y') }}</span>
                        <x-ui.btn size="xs" variant="ghost" icon="pencil" wire:click="openPremium({{ $user->id }})"
                            :aria-label="__('Edit')" />
                        <x-ui.btn size="xs" variant="ghost" icon="x-mark" wire:click="confirmRevoke({{ $user->id }})"
                            :aria-label="__('Revoke premium')" />
                    </div>
                @empty
                    <x-ui.text class="text-sm">{{ __('Nobody has premium now.') }}</x-ui.text>
                @endforelse
            </x-ui.card>

            {{-- Najwięksi wspierający --}}
            <x-ui.card class="space-y-3">
                <div>
                    <x-ui.heading>{{ __('Top supporters') }}</x-ui.heading>
                    <x-ui.text class="text-xs">{{ __('Payments in the last 12 months.') }}</x-ui.text>
                </div>
                @forelse ($this->supporters as $row)
                    <div class="flex items-center gap-2 text-sm" wire:key="supporter-{{ $row['user_id'] }}">
                        <span class="w-6 text-right tabular-nums text-ui-muted">{{ $loop->iteration }}.</span>
                        <span class="min-w-0 flex-1 truncate">{{ $row['user']?->team_name ?: $row['user']?->name }}</span>
                        <span class="tabular-nums">{{ \App\Models\Payment::money($row['total']) }}</span>
                    </div>
                @empty
                    <x-ui.text class="text-sm">{{ __('No payments in the last 12 months.') }}</x-ui.text>
                @endforelse
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal name="payment-form" class="w-full md:w-[30rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('Add payment') }}</x-ui.heading>
            <x-ui.text class="text-sm">{{ __('For example a bank transfer or a coffee on buycoffee.to. The payment is saved as paid. A bank transfer extends premium, a coffee never does.') }}</x-ui.text>

            @include('partials.payments-user-picker')

            <x-ui.radio.group wire:model="source" :label="__('Source')" variant="segmented">
                <x-ui.radio value="manual" :label="__('By hand')" />
                <x-ui.radio value="buycoffee" label="buycoffee.to" />
            </x-ui.radio.group>

            <x-ui.radio.group wire:model.live="kind" :label="__('Type')" variant="segmented">
                <x-ui.radio value="support" :label="__('Support')" />
                <x-ui.radio value="premium" :label="__('Premium')" />
            </x-ui.radio.group>

            @if ($kind === 'premium')
                <x-ui.select wire:model.live="plan" :label="__('Plan')">
                    @foreach (\App\Support\Premium::PLANS as $key => [$price, $days])
                        <x-ui.select.option :value="$key">
                            {{ \App\Support\Premium::labels()[$key] }} ({{ \App\Models\Payment::money($price) }})
                        </x-ui.select.option>
                    @endforeach
                </x-ui.select>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <x-ui.input type="number" step="0.01" min="1" wire:model="amount" :label="__('Amount')" suffix="zł" />
                <x-ui.input type="date" wire:model="paidAt" :label="__('Payment date')" />
            </div>
            <x-ui.input wire:model="note" :label="__('Note')" />

            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" wire:click="savePayment">{{ __('Save') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal name="premium-form" class="w-full md:w-[30rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('Grant premium') }}</x-ui.heading>
            <x-ui.text class="text-sm">{{ __('Premium lasts until the end of the chosen day. A past date revokes it.') }}</x-ui.text>

            @include('partials.payments-user-picker')

            <x-ui.input type="date" wire:model="premiumUntil" :label="__('Premium until')" />

            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" wire:click="savePremium">{{ __('Save') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal name="confirm-revoke" class="w-full md:w-[28rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('Revoke premium') }}</x-ui.heading>
            <x-ui.text>{{ __('Revoke premium?') }}</x-ui.text>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="revokePremium">{{ __('Revoke premium') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal name="confirm-complete" class="w-full md:w-[28rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('Confirm payment') }}</x-ui.heading>
            <x-ui.text>{{ __('Mark this payment as paid? Check first that the money is in the account. Premium will be extended.') }}</x-ui.text>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" wire:click="complete">{{ __('Confirm') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
</div>
