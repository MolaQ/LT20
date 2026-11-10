{{-- Mała etykieta (punkty, premium, status). variant: default, club, gold, good, bad. --}}
@props(['variant' => 'default'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 font-display text-[13px] font-bold uppercase tracking-wider',
    match ($variant) {
        'club' => 'bg-club text-club-ink',
        'gold' => 'bg-gold-soft text-gold',
        'good' => 'bg-good-soft text-good',
        'bad' => 'bg-bad-soft text-bad',
        default => 'bg-ui-raised text-ui-ink',
    },
]) }}>{{ $slot }}</span>
