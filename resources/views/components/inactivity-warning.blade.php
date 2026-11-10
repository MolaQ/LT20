{{--
    Ostrzeżenie po nieoddanych typach (wariant „kartki”, App\Support\Inactivity): każda kolejka z rzędu bez typu
    to żółta kartka, piąte miejsce obrysowane na czerwono (przy 5 zespół od nowego sezonu przejmuje bot).
    Od 4 kartek „Ostatnia szansa” w czerwonej ramce; konto premium widzi tarczę zamiast ostrzeżenia.
    Nic nie pokazuje, gdy seria jest zerowa albo oglądający nie gra. Na stronie głównej i w typowaniu.
--}}
@props(['tipLink' => true])

@php
    $user = auth()->user();
    $level = \App\Support\Inactivity::level($user);
    $streak = $level ? \App\Support\Inactivity::streak($user) : 0;
    $limit = \App\Support\Inactivity::LIMIT;
@endphp

@if ($level === 'protected')
    <div {{ $attributes->class('flex items-center gap-3 rounded-2xl border border-purple-200 bg-purple-50 px-4 py-3 dark:border-purple-500/30 dark:bg-purple-500/10') }} role="status">
        <x-ui.icon name="shield-check" class="size-7 shrink-0 text-purple-600 dark:text-purple-300" />
        <div class="min-w-0 text-sm">
            <p class="font-semibold text-purple-700 dark:text-purple-300">{{ __('Premium protects your team') }}</p>
            <p class="text-ui-muted">{{ trans_choice(':count matchday without a tip – with valid premium your team will not become a bot.|:count matchdays without a tip – with valid premium your team will not become a bot.', $streak, ['count' => $streak]) }}</p>
        </div>
    </div>
@elseif ($level)
    @php
        $last = $level === 'last';
    @endphp
    <div {{ $attributes->class([
        'flex flex-col gap-3 rounded-2xl px-4 py-3 shadow-sm sm:flex-row sm:items-center',
        'border-[1.5px] border-red-300 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10' => $last,
        'border border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10' => ! $last,
    ]) }} role="alert">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            {{-- Kartki: żółte za kolejki bez typu, puste miejsca, ostatnie obrysowane na czerwono. --}}
            <div class="flex shrink-0 items-center gap-1" aria-hidden="true">
                @for ($i = 1; $i <= $limit; $i++)
                    @if ($i <= $streak)
                        <span class="h-6 w-4 -rotate-6 rounded-[3px] bg-gradient-to-br from-yellow-300 to-yellow-500 shadow-sm"></span>
                    @else
                        <span @class(['h-6 w-4 -rotate-6 rounded-[3px] border-[1.5px] border-dashed', 'border-red-400' => $i === $limit, 'border-zinc-300 dark:border-zinc-600' => $i !== $limit])></span>
                    @endif
                @endfor
            </div>
            <div class="min-w-0 text-sm">
                @if ($last)
                    <p class="font-semibold text-red-700 dark:text-red-300">{{ __('Last chance!') }}</p>
                    <p class="text-ui-muted">{{ trans_choice(':count matchday in a row without a tip. One more and from the next season a bot takes over your team.|:count matchdays in a row without a tip. One more and from the next season a bot takes over your team.', $streak, ['count' => $streak]) }}</p>
                @else
                    <p class="font-semibold">{{ trans_choice(':count matchday in a row without a tip|:count matchdays in a row without a tip', $streak, ['count' => $streak]) }}</p>
                    <p class="text-ui-muted">{{ trans_choice('One more and from the next season a bot takes over your team.|:count more and from the next season a bot takes over your team.', $limit - $streak, ['count' => $limit - $streak]) }}</p>
                @endif
            </div>
        </div>
        @if ($tipLink)
            <a href="{{ route('tips') }}" wire:navigate @class([
                'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110',
                'bg-red-700' => $last,
                'bg-club' => ! $last,
            ])>{{ __('Tip now') }}</a>
        @endif
    </div>
@endif
