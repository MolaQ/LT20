<?php

use App\Enums\Permission;
use App\Enums\TeamNameRequestStatus;
use App\Models\TeamNameChangeRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    // Odrzucanie
    public ?int $rejectId = null;
    public string $rejectUserName = '';
    public string $rejectReason = '';

    public function render(): View
    {
        return $this->view()->title(__('Team requests'));
    }

    /* ------------------------------------------------------------------
     | Dane
     * ----------------------------------------------------------------*/

    #[Computed]
    public function stats(): array
    {
        $counts = TeamNameChangeRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[TeamNameRequestStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[TeamNameRequestStatus::Approved->value] ?? 0),
            'rejected' => (int) ($counts[TeamNameRequestStatus::Rejected->value] ?? 0),
        ];
    }

    #[Computed]
    public function requests()
    {
        $statuses = array_column(TeamNameRequestStatus::cases(), 'value');

        return TeamNameChangeRequest::query()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when(in_array($this->status, $statuses, true), fn($q) => $q->where('status', $this->status))
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';

                $q->where(function ($q) use ($term) {
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhere('requested_team_name', 'like', $term);
                });
            })
            ->orderBy('created_at', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderBy('id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate(10, pageName: 'requestsPage');
    }

    public function sort(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage('requestsPage');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('requestsPage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('requestsPage');
    }

    /* ------------------------------------------------------------------
     | Zatwierdzanie
     * ----------------------------------------------------------------*/

    public function approve(int $id): void
    {
        $this->authorizeReview();

        try {
            $error = DB::transaction(function () use ($id): ?string {
                $request = TeamNameChangeRequest::query()->lockForUpdate()->findOrFail($id);

                if ($request->status !== TeamNameRequestStatus::Pending) {
                    return __('This request has already been handled.');
                }

                // Ponowne sprawdzenie unikalności: nazwa mogła zostać zajęta po wysłaniu prośby.
                $taken = $this->takenFields($request);

                if ($taken !== []) {
                    return __('Already taken by someone else: :fields.', ['fields' => implode(', ', $taken)]);
                }

                $user = User::query()->lockForUpdate()->findOrFail($request->user_id);

                $old = [
                    'team_name' => $user->team_name,
                ];

                $new = [
                    'team_name' => $request->requested_team_name,
                ];

                $user->update($new);

                $request->update([
                    'status' => TeamNameRequestStatus::Approved,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'reject_reason' => null,
                ]);

                Audit::log('team_name.approved', $user, $old, $new, __('Request #:id approved.', ['id' => $request->id]));

                return null;
            });
        } catch (UniqueConstraintViolationException) {
            $error = __('One of the values has just been taken by someone else.');
        }

        if ($error !== null) {
            $this->dispatch('ui-toast', text: $error, variant: 'danger');

            return;
        }

        unset($this->requests, $this->stats);
        $this->dispatch('ui-toast', text: __('Request approved.'), variant: 'success');
    }

    /** Zwraca etykiety pól, które zajmuje już ktoś inny (bez względu na wielkość liter). */
    private function takenFields(TeamNameChangeRequest $request): array
    {
        $checks = [
            'team_name' => [$request->requested_team_name, __('Team name')],
        ];

        $taken = [];

        // Nazwy kolumn pochodzą ze stałej tablicy powyżej, więc interpolacja w SQL jest bezpieczna.
        foreach ($checks as $column => [$value, $label]) {
            $exists = DB::table('users')
                ->whereRaw("LOWER({$column}) = ?", [mb_strtolower(trim((string) $value))])
                ->where('id', '!=', $request->user_id)
                ->exists();

            if ($exists) {
                $taken[] = $label;
            }
        }

        return $taken;
    }

    /* ------------------------------------------------------------------
     | Odrzucanie
     * ----------------------------------------------------------------*/

    public function confirmReject(int $id): void
    {
        $this->authorizeReview();

        $request = TeamNameChangeRequest::query()->pending()->with('user:id,name')->findOrFail($id);

        $this->rejectId = $request->id;
        $this->rejectUserName = (string) $request->user?->name;
        $this->rejectReason = '';
        $this->resetValidation();

        $this->dispatch('ui-sheet', name: 'reject-request');
    }

    public function reject(): void
    {
        $this->authorizeReview();

        $this->validate([
            'rejectReason' => ['nullable', 'string', 'max:255'],
        ], attributes: ['rejectReason' => __('Reason')]);

        $error = DB::transaction(function (): ?string {
            $request = TeamNameChangeRequest::query()->lockForUpdate()->findOrFail($this->rejectId);

            if ($request->status !== TeamNameRequestStatus::Pending) {
                return __('This request has already been handled.');
            }

            $user = User::query()->find($request->user_id);
            $reason = trim($this->rejectReason) !== '' ? trim($this->rejectReason) : null;

            $request->update([
                'status' => TeamNameRequestStatus::Rejected,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'reject_reason' => $reason,
            ]);

            Audit::log(
                'team_name.rejected',
                $user,
                [
                    'team_name' => $user?->team_name,
                ],
                [
                    'team_name' => $request->requested_team_name,
                ],
                __('Request #:id rejected.', ['id' => $request->id]) . ($reason ? ' ' . $reason : ''),
            );

            return null;
        });

        $this->dispatch('ui-sheet', name: 'reject-request', open: false);
        $this->reset('rejectId', 'rejectUserName', 'rejectReason');
        unset($this->requests, $this->stats);

        if ($error !== null) {
            $this->dispatch('ui-toast', text: $error, variant: 'warning');

            return;
        }

        $this->dispatch('ui-toast', text: __('Request rejected.'), variant: 'success');
    }

    /* ------------------------------------------------------------------
     | Akcje Livewire to osobne żądania, więc sprawdzamy uprawnienie w każdej.
     * ----------------------------------------------------------------*/

    private function authorizeReview(): void
    {
        abort_unless(auth()->user()?->can(Permission::TeamChangeName->value), 403);
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div>
        <x-ui.heading size="xl" level="1">{{ __('Team requests') }}</x-ui.heading>
        <x-ui.subheading>{{ __('Review requests to change team names.') }}</x-ui.subheading>
    </div>

    {{-- Statystyki --}}
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="clock" class="size-8 text-amber-500" />
            <div>
                <x-ui.text>{{ __('Pending') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['pending'] }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="check-circle" class="size-8 text-green-500" />
            <div>
                <x-ui.text>{{ __('Approved') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['approved'] }}</x-ui.heading>
            </div>
        </x-ui.card>
        <x-ui.card class="flex items-center gap-4">
            <x-ui.icon name="x-circle" class="size-8 text-red-500" />
            <div>
                <x-ui.text>{{ __('Rejected') }}</x-ui.text>
                <x-ui.heading size="xl">{{ $this->stats['rejected'] }}</x-ui.heading>
            </div>
        </x-ui.card>
    </div>

    {{-- Filtry --}}
    <div class="flex flex-wrap items-center gap-4">
        <div class="w-full sm:w-72">
            <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="__('Search by user or team...')" clearable />
        </div>

        <div class="w-full sm:w-56">
            <x-ui.select wire:model.live="status">
                <x-ui.select.option value="">{{ __('All statuses') }}</x-ui.select.option>
                <x-ui.select.option value="pending">{{ __('Pending') }}</x-ui.select.option>
                <x-ui.select.option value="approved">{{ __('Approved') }}</x-ui.select.option>
                <x-ui.select.option value="rejected">{{ __('Rejected') }}</x-ui.select.option>
            </x-ui.select>
        </div>
    </div>

    {{-- Tabela --}}
    <x-ui.table :paginate="$this->requests">
        <x-ui.table.columns>
            <x-ui.table.column>{{ __('User') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Requested change') }}</x-ui.table.column>
            <x-ui.table.column>{{ __('Status') }}</x-ui.table.column>
            <x-ui.table.column sortable sorted direction="{{ $sortDirection }}" wire:click="sort">
                {{ __('Sent') }}
            </x-ui.table.column>
            <x-ui.table.column />
        </x-ui.table.columns>

        <x-ui.table.rows>
            @forelse ($this->requests as $req)
                <x-ui.table.row :key="'request-'.$req->id">
                    <x-ui.table.cell>
                        <div class="font-medium">{{ $req->user?->name ?? '—' }}</div>
                        <div class="text-sm text-ui-muted">{{ $req->user?->email }}</div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <div class="space-y-1 text-sm">
                            @foreach ([['team_name', __('Team name')]] as [$field, $label])
                                @php
                                    $old = $req->{'current_' . $field};
                                    $new = $req->{'requested_' . $field};
                                @endphp
                                <div class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="text-ui-muted">{{ $label }}:</span>
                                    @if ($old !== $new)
                                        <span class="text-zinc-400 line-through">{{ $old ?? '—' }}</span>
                                        <span>&rarr;</span>
                                        <span class="font-medium text-green-700 dark:text-green-400">{{ $new }}</span>
                                    @else
                                        <span>{{ $new }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        @php
                            $color = match ($req->status) {
                                \App\Enums\TeamNameRequestStatus::Approved => 'green',
                                \App\Enums\TeamNameRequestStatus::Rejected => 'red',
                                default => 'amber',
                            };
                            $statusLabel = match ($req->status) {
                                \App\Enums\TeamNameRequestStatus::Approved => __('Approved'),
                                \App\Enums\TeamNameRequestStatus::Rejected => __('Rejected'),
                                default => __('Pending'),
                            };
                        @endphp
                        <x-ui.badge size="sm" :color="$color">{{ $statusLabel }}</x-ui.badge>

                        @if ($req->reviewed_at)
                            <div class="mt-1 text-xs text-ui-muted">
                                {{ $req->reviewer?->name ?? '—' }}, {{ $req->reviewed_at->format('Y-m-d H:i') }}
                            </div>
                        @endif
                        @if ($req->reject_reason)
                            <div class="mt-1 text-xs text-ui-muted">{{ $req->reject_reason }}</div>
                        @endif
                    </x-ui.table.cell>

                    <x-ui.table.cell class="text-ui-muted">{{ $req->created_at->format('Y-m-d H:i') }}</x-ui.table.cell>

                    <x-ui.table.cell align="end">
                        @if ($req->status === \App\Enums\TeamNameRequestStatus::Pending)
                            <div class="flex justify-end gap-2">
                                <x-ui.btn size="sm" variant="primary" icon="check" x-on:click="$dispatch('confirm-action', { message: @js(__('Approve this change?')), run: () => $wire.approve({{ $req->id }}) })">
                                    {{ __('Approve') }}
                                </x-ui.btn>
                                <x-ui.btn size="sm" variant="ghost" icon="x-mark" wire:click="confirmReject({{ $req->id }})">
                                    {{ __('Reject') }}
                                </x-ui.btn>
                            </div>
                        @endif
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.row>
                    <x-ui.table.cell colspan="5" class="py-10 text-center text-ui-muted">
                        {{ __('No requests match your filters.') }}
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforelse
        </x-ui.table.rows>
    </x-ui.table>

    {{-- Modal: odrzucenie --}}
    <x-ui.modal name="reject-request" class="w-full md:w-[28rem]">
        <form wire:submit="reject" class="space-y-6">
            <div>
                <x-ui.heading size="lg">{{ __('Reject request') }}</x-ui.heading>
                <x-ui.text class="mt-1">{{ $rejectUserName }}</x-ui.text>
            </div>

            <x-ui.textarea wire:model="rejectReason" :label="__('Reason (optional)')" rows="3" />

            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost" type="button">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" type="submit">{{ __('Reject') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.modal>
</div>