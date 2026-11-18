{{--
    „Zaproś kibiców” (kampania, 18.11.2026): link zapraszający gracza (App\Support\Referrals) z kopiowaniem
    i udostępnieniem na Facebooku. Za każdych 3 zaproszonych z potwierdzonym e-mailem: 2 tygodnie premium.
    Tylko dla zalogowanych z rolą gracza.
--}}
@php
    $me = auth()->user();
@endphp

@if ($me && $me->roles->isNotEmpty())
    @php
        $link = \App\Support\Referrals::link($me);
        $stats = \App\Support\Referrals::stats($me);
        $per = \App\Support\Referrals::PER_REWARD;
    @endphp
    <div {{ $attributes->class('relative overflow-hidden rounded-2xl bg-linear-to-br from-[#061331] via-[#0b2f74] to-[#1d4ed8] p-4 text-white shadow-sm') }}
        x-data="{ copied: false, copy() { navigator.clipboard?.writeText(@js($link)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
        <div class="flex items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber-400 text-[#0b2f74]">
                <x-ui.icon name="user-plus" variant="mini" class="size-6" />
            </span>
            <div class="min-w-0 space-y-1">
                <p class="font-display text-lg font-bold leading-tight">{{ __('Invite fans') }}</p>
                <p class="text-sm leading-snug text-blue-100/90">
                    {{ __('For every :count players who join from your link and confirm their e-mail you get :days days of premium.', ['count' => $per, 'days' => \App\Support\Referrals::REWARD_DAYS]) }}
                </p>
            </div>
        </div>

        {{-- Postęp do następnej nagrody --}}
        <div class="mt-3 flex items-center gap-2">
            @for ($i = 0; $i < $per; $i++)
                <span @class(['h-2 flex-1 rounded-full', 'bg-amber-400' => $i < $stats['progress'], 'bg-white/15' => $i >= $stats['progress']])></span>
            @endfor
        </div>
        <p class="mt-1.5 text-xs text-blue-100/80">
            {{ __('Confirmed: :confirmed · earned: :days days of premium', ['confirmed' => $stats['confirmed'], 'days' => $stats['days']]) }}
        </p>

        <input type="text" readonly value="{{ $link }}" aria-label="{{ __('Invite fans') }}" x-on:focus="$el.select()"
            class="mt-3 h-9 w-full truncate rounded-lg border border-white/20 bg-white/10 px-3 text-xs font-medium text-white outline-none focus:border-amber-400">
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" x-on:click="copy()"
                class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-white px-3 text-sm font-bold text-[#0b2f74] shadow transition hover:-translate-y-0.5">
                <x-ui.icon name="link" variant="micro" class="size-4" />
                <span x-text="copied ? @js(__('Copied')) : @js(__('Copy link'))">{{ __('Copy link') }}</span>
            </button>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($link) }}" target="_blank" rel="noopener"
                class="flex h-10 items-center justify-center gap-1.5 rounded-xl border border-white/30 bg-white/10 px-3 text-sm font-bold transition hover:bg-white/20">
                <x-ui.icon name="share" variant="micro" class="size-4" />
                {{ __('Share on Facebook') }}
            </a>
        </div>
    </div>
@endif
