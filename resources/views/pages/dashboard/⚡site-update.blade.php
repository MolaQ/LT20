<?php

use App\Support\Maintenance;
use App\Support\SiteUpdate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Aktualizacja strony z panelu (App\Support\SiteUpdate): wgranie paczki typer-kod.zip w kawałkach, jej sprawdzenie
 * i zatwierdzenie instalacji hasłem. Samą instalację robi cron (narzedzia/instaluj-na-serwerze.sh) po znalezieniu
 * pliku flagi z sumą kontrolną paczki; ekran pokazuje jej przebieg i log instalatora.
 */
new class extends Component {
    public string $password = '';

    /** Zgoda na wgranie paczki starszej niż zainstalowana. */
    public bool $allowOlder = false;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function render(): View
    {
        return $this->view()->title(__('Site update'));
    }

    public function install(): void
    {
        $package = SiteUpdate::package();

        if ($package && $package['starsza'] && ! $this->allowOlder) {
            $this->addError('allowOlder', __('This package is older than the installed one. Tick the consent if you really want to install it.'));

            return;
        }

        $this->confirmed(fn () => SiteUpdate::requestInstall(auth()->user()), __('Install approved. It starts with the next cron run.'));
    }

    public function rollback(): void
    {
        $this->confirmed(fn () => SiteUpdate::requestRollback(auth()->user()), __('Restoring the previous version is queued. It starts with the next cron run.'));
    }

    public function cancel(): void
    {
        $this->authorizeAdmin();

        try {
            SiteUpdate::cancel();
            $this->dispatch('ui-toast', variant: 'success', text: __('Package removed.'));
        } catch (RuntimeException $error) {
            $this->dispatch('ui-toast', variant: 'danger', text: $error->getMessage());
        }
    }

    /** Operacje zmieniające kod strony wymagają ponownego podania hasła. */
    private function confirmed(callable $action, string $done): void
    {
        $this->authorizeAdmin();

        if (! Hash::check($this->password, (string) auth()->user()->password)) {
            $this->addError('password', __('The password is incorrect.'));

            return;
        }

        try {
            $action();
            $this->dispatch('ui-toast', variant: 'success', text: $done);
        } catch (RuntimeException $error) {
            $this->dispatch('ui-toast', variant: 'danger', text: $error->getMessage());
        }

        $this->reset('password', 'allowOlder');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Maintenance::canBypass(auth()->user()), 403);
    }
}; ?>

@php
    $installed = SiteUpdate::installedVersion();
    $package = SiteUpdate::package();
    $flag = SiteUpdate::flag();
    $locked = SiteUpdate::locked();
    $status = SiteUpdate::status();
    $log = SiteUpdate::logTail();
    $canRollback = is_file(SiteUpdate::rollbackPath());
    // Po podmianie plików cron jeszcze migruje i optymalizuje: ekran odświeża się, aż zapisze wynik.
    $busy = $flag || $locked || ($status['stan'] ?? '') === 'instalacja';
    $rollingBack = ($flag['typ'] ?? '') === 'cofnij';
    $megabytes = fn (int $bytes) => number_format($bytes / 1024 / 1024, 1, ',', ' ').' MB';
    $statusColor = match ($status['stan'] ?? '') {
        'gotowe' => 'green',
        'blad' => 'red',
        default => 'zinc',
    };
@endphp

<div class="max-w-3xl space-y-6" @if ($busy) wire:poll.5s @endif>
    <div>
        <x-ui.heading size="xl">{{ __('Site update') }}</x-ui.heading>
        <x-ui.text>{{ __('Upload the typer-kod.zip package built with paczka-na-serwer.bat. The panel checks it, you approve the install with your password and the server cron installs it within a minute.') }}</x-ui.text>
    </div>

    <x-ui.card class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ui-muted">{{ __('Installed version') }}:
            <strong class="tabular-nums text-ui-ink">{{ $installed ?? __('unknown (package without a number)') }}</strong></p>
        @if ($status && ! $busy)
            <x-ui.badge :color="$statusColor">{{ $status['opis'] ?? '' }} · {{ $status['czas'] ?? '' }}</x-ui.badge>
        @endif
    </x-ui.card>

    @if ($busy)
        {{-- Zatwierdzone: czekamy na cron albo instalacja właśnie trwa --}}
        <x-ui.card class="space-y-3" wire:key="site-update-busy">
            <p class="flex items-center gap-2 font-semibold">
                <span class="size-2.5 animate-pulse rounded-full bg-amber-500" aria-hidden="true"></span>
                @if (! $flag && ! $locked)
                    {{ __('Files replaced, the installer finishes the update (migrations, cache)…') }}
                @elseif ($locked)
                    {{ $rollingBack ? __('Restoring the previous version…') : __('Installing the package…') }}
                @else
                    {{ $rollingBack ? __('Restoring the previous version waits for the cron.') : __('Package :version waits for the cron.', ['version' => $flag['wersja'] ?? '']) }}
                @endif
            </p>
            @if ($flag)<x-ui.text class="text-xs">{{ __('Approved by :name · :time. While the files are replaced the site shows a technical break page. This page refreshes itself.', ['name' => $flag['kto'] ?? '—', 'time' => $flag['czas'] ?? '']) }}</x-ui.text>@endif
            @if ($flag && ! $locked)
                <x-ui.btn variant="danger" size="sm" icon="x-mark"
                    x-on:click="$dispatch('confirm-action', { message: @js(__('Withdraw the approval and remove the uploaded package?')), danger: true, run: () => $wire.cancel() })">
                    {{ __('Withdraw and remove the package') }}</x-ui.btn>
            @endif
        </x-ui.card>
    @elseif ($package)
        {{-- Paczka wgrana i sprawdzona: decyzja admina --}}
        <x-ui.card class="space-y-4" wire:key="site-update-package">
            <x-ui.heading size="lg">{{ __('Package ready to install') }}</x-ui.heading>
            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-ui-muted">{{ __('Package version') }}</dt><dd class="font-semibold tabular-nums">{{ $package['wersja'] }}</dd></div>
                <div><dt class="text-xs text-ui-muted">{{ __('Size and files') }}</dt><dd>{{ $megabytes((int) $package['rozmiar']) }} · {{ __(':count files', ['count' => number_format((int) $package['pliki'], 0, ',', ' ')]) }}</dd></div>
                <div><dt class="text-xs text-ui-muted">{{ __('Uploaded by') }}</dt><dd>{{ $package['kto'] }} · {{ $package['wgrano'] }}</dd></div>
                <div class="min-w-0"><dt class="text-xs text-ui-muted">{{ __('Checksum (SHA-256)') }}</dt><dd class="truncate font-mono text-xs" title="{{ $package['sha256'] }}">{{ $package['sha256'] }}</dd></div>
            </dl>

            @if ($package['migracje'])
                <x-ui.callout variant="warning" icon="exclamation-triangle" :heading="__('The package has database migrations (:count).', ['count' => count($package['migracje'])])">
                    <ul class="list-inside list-disc text-xs">
                        @foreach ($package['migracje'] as $migration)<li class="break-all">{{ $migration }}</li>@endforeach
                    </ul>
                </x-ui.callout>
            @else
                <x-ui.callout variant="success" icon="check-circle">{{ __('The package has no new database migrations.') }}</x-ui.callout>
            @endif

            @if ($package['starsza'])
                <x-ui.callout variant="danger" icon="exclamation-triangle">
                    <x-ui.checkbox wire:model="allowOlder" :label="__('This package is older than the installed one (:version).', ['version' => $installed])"
                        :description="__('Tick it if you really want to install it.')" />
                    @error('allowOlder')<p class="text-sm text-bad">{{ $message }}</p>@enderror
                </x-ui.callout>
            @endif

            <form wire:submit="install" class="flex flex-wrap items-end gap-3 border-t border-ui-line pt-4">
                <x-ui.input type="password" wire:model="password" :label="__('Confirm with your password')" autocomplete="current-password" viewable class="min-w-0 flex-1" />
                <x-ui.btn type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">{{ __('Install') }}</x-ui.btn>
                <x-ui.btn variant="danger" icon="trash"
                    x-on:click="$dispatch('confirm-action', { message: @js(__('Remove the uploaded package?')), danger: true, run: () => $wire.cancel() })">{{ __('Remove the package') }}</x-ui.btn>
            </form>
        </x-ui.card>
    @else
        {{-- Wgrywanie w kawałkach: omija limit wielkości pliku na hostingu, a każdy kawałek ponawia się przy błędzie sieci --}}
        <x-ui.card class="space-y-3" wire:key="site-update-upload"
            x-data="{ busy: false, done: 0, total: 0, error: '', name: '',
                async send(file) {
                    if (! file) { return; }
                    if (! /\.zip$/i.test(file.name)) { this.error = @js(__('Choose the typer-kod.zip file.')); return; }
                    this.busy = true; this.error = ''; this.name = file.name;
                    const size = {{ SiteUpdate::CHUNK_BYTES }}; const upload = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
                    this.total = Math.max(1, Math.ceil(file.size / size)); this.done = 0;
                    for (let index = 0; index < this.total; index++) {
                        const body = new FormData();
                        body.append('upload', upload); body.append('index', index); body.append('total', this.total);
                        body.append('chunk', file.slice(index * size, (index + 1) * size), 'czesc.bin');
                        let ok = false; let message = @js(__('The connection was interrupted. Try again.'));
                        for (let attempt = 0; attempt < 4 && ! ok; attempt++) {
                            try {
                                const response = await fetch(@js(route('dashboard.site-update.chunk')), { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' } });
                                if (response.ok) { ok = true; break; }
                                if ([403, 413, 419, 422].includes(response.status)) { message = (await response.json().catch(() => ({}))).message ?? @js(__('The server refused the file. Refresh the page and try again.')) + ' (' + response.status + ')'; break; }
                            } catch (error) {}
                            await new Promise((resolve) => setTimeout(resolve, 1500 * (attempt + 1)));
                        }
                        if (! ok) { this.error = message; this.busy = false; return; }
                        this.done = index + 1;
                    }
                    $wire.$refresh(); this.busy = false;
                } }">
            <x-ui.heading size="lg">{{ __('Upload a package') }}</x-ui.heading>
            <label class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-ui-line px-4 py-8 text-center transition hover:border-club hover:bg-ui-raised"
                :class="busy && 'pointer-events-none opacity-60'" x-on:dragover.prevent x-on:drop.prevent="send($event.dataTransfer.files[0])">
                <x-ui.icon name="arrow-up-tray" class="size-8 text-ui-muted" />
                <span class="text-sm font-semibold">{{ __('Choose or drop the typer-kod.zip file here') }}</span>
                <span class="text-xs text-ui-muted">{{ __('Uploading changes nothing on the site. You approve the install in the next step.') }}</span>
                <input type="file" accept=".zip" class="sr-only" x-on:change="send($event.target.files[0]); $event.target.value = ''">
            </label>
            <div x-show="busy || done" x-cloak>
                <div class="flex justify-between text-xs text-ui-muted"><span class="truncate" x-text="name"></span><span class="tabular-nums" x-text="Math.round(done * 100 / Math.max(1, total)) + '%'"></span></div>
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-ui-raised"><div class="h-full rounded-full bg-club transition-all" :style="'width:' + (done * 100 / Math.max(1, total)) + '%'"></div></div>
            </div>
            <p x-show="error" x-cloak class="rounded-xl bg-bad-soft p-3 text-sm text-bad" x-text="error" role="alert"></p>
        </x-ui.card>

        @if ($canRollback)
            <x-ui.card>
                <details>
                    <summary class="cursor-pointer font-semibold">{{ __('Restore the previous code version') }}</summary>
                    <x-ui.text class="mt-2">{{ __('Before every update from the panel the installer keeps the previous version of the site files. Restoring brings back only the code; the database stays as it is.') }}</x-ui.text>
                    <form wire:submit="rollback" class="mt-3 flex flex-wrap items-end gap-3">
                        <x-ui.input type="password" wire:model="password" :label="__('Confirm with your password')" autocomplete="current-password" viewable class="min-w-0 flex-1" />
                        <x-ui.btn type="submit" variant="danger" icon="arrow-uturn-left">{{ __('Restore the previous version') }}</x-ui.btn>
                    </form>
                </details>
            </x-ui.card>
        @endif
    @endif

    @if ($log !== '')
        <x-ui.card>
            <details @if ($busy || ($status['stan'] ?? '') === 'blad') open @endif>
                <summary class="cursor-pointer font-semibold">{{ __('Installer log (last lines)') }}</summary>
                <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap rounded-xl bg-zinc-900 p-3 text-xs leading-relaxed text-zinc-100">{{ $log }}</pre>
            </details>
        </x-ui.card>
    @endif
</div>
