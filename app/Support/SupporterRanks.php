<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\SupporterRank;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rangi wspierających (17.11.2026): ranga zależy od sumy opłaconych wpłat z 12 miesięcy (Supporters::ranking), progi
 * i kolory ustawia admin w panelu wpłat. Ranga daje delikatne tło przy nazwie drużyny (<x-supporter-name>) i miejsce
 * na ścianie wspierających (/support). Kwot nie pokazujemy nigdzie poza panelem. Gracz może się ukryć
 * (users.show_as_supporter); wpłaty bez konta to „Anonimowy kibol”.
 */
final class SupporterRanks
{
    /** @return Collection<int, SupporterRank> rangi od najniższego progu */
    public static function all(): Collection
    {
        return once(fn () => SupporterRank::orderBy('threshold')->orderBy('id')->get());
    }

    /** Najwyższa ranga, na którą wystarcza suma (grosze), albo null. */
    public static function forTotal(int $total): ?SupporterRank
    {
        return self::all()->filter(fn (SupporterRank $rank) => $rank->threshold <= $total)->last();
    }

    /**
     * Rangi graczy, którzy się nie ukryli: user_id => ranga.
     *
     * @return array<int, SupporterRank>
     */
    public static function byUser(): array
    {
        return once(function (): array {
            $ranking = Supporters::ranking();
            $visible = User::whereIn('id', $ranking->pluck('user_id'))->where('show_as_supporter', true)->pluck('id')->flip();
            $ranks = [];
            foreach ($ranking as $row) {
                $rank = self::forTotal($row['total']);
                if ($rank && $visible->has($row['user_id'])) {
                    $ranks[$row['user_id']] = $rank;
                }
            }

            return $ranks;
        });
    }

    public static function forUser(?int $userId): ?SupporterRank
    {
        return $userId ? (self::byUser()[$userId] ?? null) : null;
    }

    /**
     * Ściana wspierających: od największej sumy z 12 miesięcy (przy równej wcześniejsza wpłata wyżej), bez kwot.
     * Każda wpłata bez konta to osobny „Anonimowy kibol”.
     *
     * @return list<array{user: User|null, name: string, rank: SupporterRank}>
     */
    public static function wall(): array
    {
        $ranks = self::byUser();
        $users = User::whereIn('id', array_keys($ranks))->get(['id', 'name', 'team_name'])->keyBy('id');

        $entries = [];
        foreach (Supporters::ranking() as $row) {
            $user = $users->get($row['user_id']);
            if ($user && isset($ranks[$row['user_id']])) {
                $entries[] = ['user' => $user, 'name' => (string) ($user->team_name ?: $user->name), 'rank' => $ranks[$row['user_id']], 'total' => $row['total'], 'at' => $row['last_paid_at']];
            }
        }

        $anonymous = Payment::query()->paid()->whereNull('user_id')
            ->whereBetween('paid_at', [now()->subMonths(Supporters::MONTHS), now()])
            ->get(['amount', 'paid_at']);
        foreach ($anonymous as $payment) {
            $rank = self::forTotal($payment->amount);
            if ($rank) {
                $entries[] = ['user' => null, 'name' => (string) __('Anonymous kibol'), 'rank' => $rank, 'total' => $payment->amount, 'at' => (string) $payment->paid_at];
            }
        }

        usort($entries, fn (array $a, array $b) => [$b['total'], $a['at']] <=> [$a['total'], $b['at']]);

        return array_map(fn (array $e) => ['user' => $e['user'], 'name' => $e['name'], 'rank' => $e['rank']], $entries);
    }
}
