{{--
    Zmiana miejsca w wyścigu o nagrody po ostatniej kolejce: ▲ w górę, ▼ w dół, – bez zmiany, nic bez porównania.
    Zmienne: $move (int|null), $class (dodatkowe klasy).
--}}
@if ($move !== null)
    <span @class([$class, 'text-xs font-bold tabular-nums', 'text-green-600 dark:text-green-400' => $move > 0, 'text-red-600 dark:text-red-400' => $move < 0, 'text-ui-muted' => $move === 0])
        title="{{ __('Change after the last matchday') }}">{{ $move > 0 ? '▲' . $move : ($move < 0 ? '▼' . abs($move) : '–') }}</span>
@endif
