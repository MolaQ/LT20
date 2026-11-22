{{--
    Odpowiedzi gracza na pytania kolejki po wynikach (App\Support\Rivals::questions, klucz items):
    pytanie, strona bonusu, odpowiedź gracza i rozstrzygnięcie, a pod listą informacja, którą stronę bonusu wyzerowało pudło.
    Użycie: <x-question-results :info="$side['questions']" />
--}}
@props(['info'])

<div {{ $attributes->class('space-y-1.5 text-left') }}>
    <ul class="space-y-1">
        @foreach ($info['items'] as $item)
            @php
                $zeroed = $item['result'] === 'hit' && $item['side'] !== null && ($info[$item['side'] . '_missed'] ?? false);
            @endphp
            <li @class(['flex items-start gap-2 rounded-lg px-2 py-1.5',
                'bg-emerald-500/10' => $item['result'] === 'hit' && !$zeroed,
                'bg-red-500/10' => $item['result'] === 'miss',
                'bg-ui-raised' => !in_array($item['result'], ['hit', 'miss'], true) || $zeroed])>
                @if ($item['side'] !== null)
                    <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded text-white" style="background: var(--q-{{ $item['side'] }})"
                        title="{{ $item['side'] === 'attack' ? __('Offensive bonus') : __('Defensive bonus') }}">
                        <x-ui.icon :name="$item['side'] === 'attack' ? 'swords' : 'shield'" variant="micro" class="size-2.5" />
                    </span>
                @else
                    <span class="mt-0.5 size-4 shrink-0 rounded border border-dashed border-ui-line" aria-hidden="true"></span>
                @endif

                <span class="min-w-0 flex-1 leading-snug">{{ $item['text'] }}</span>

                <span class="flex shrink-0 items-center gap-1">
                    @if ($item['answer'] === null)
                        <span class="text-ui-muted">{{ __('No answer') }}</span>
                    @else
                        <span class="rounded bg-ui-surface px-1.5 py-0.5 font-semibold">{{ $item['answer'] ? __('Yes') : __('No') }}</span>
                    @endif

                    @switch($item['result'])
                        @case('hit')
                            <x-ui.icon name="check-circle" variant="micro" @class(['size-4', 'text-emerald-600 dark:text-emerald-400' => !$zeroed, 'text-ui-muted' => $zeroed]) />
                            @break
                        @case('miss')
                            <x-ui.icon name="x-circle" variant="micro" class="size-4 text-red-600 dark:text-red-400" />
                            @break
                        @case('void')
                            <span title="{{ __('Voided') }}"><x-ui.icon name="no-symbol" variant="micro" class="size-4 text-ui-muted" /></span>
                            @break
                        @case('pending')
                            <span title="{{ __('Waiting for the answer') }}"><x-ui.icon name="clock" variant="micro" class="size-4 text-ui-muted" /></span>
                            @break
                    @endswitch
                </span>
            </li>
        @endforeach
    </ul>

    @foreach (['attack' => __('A miss in attack wiped out the offensive bonus.'), 'defense' => __('A miss in defence wiped out the defensive bonus.')] as $key => $note)
        @if ($info[$key . '_missed'] ?? false)
            <p class="flex items-center gap-1.5 font-medium text-red-600 dark:text-red-400">
                <x-ui.icon name="x-circle" variant="micro" class="size-3.5" />{{ $note }}
            </p>
        @endif
    @endforeach
</div>
