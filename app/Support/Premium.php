<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\Permission\Models\Role;

/**
 * Premium (regulamin, punkt 11): rola z datą wygaśnięcia. Typowanie jest zawsze darmowe, premium daje
 * tylko dodatki (np. podgląd rywali i statystyki typów). Nowa wpłata przedłuża premium. Cegiełka (wsparcie dowolną kwotą) daje
 * premium według najwyższej opcji cennika, na którą wystarcza kwoty. Admin może nadać premium ręcznie.
 * Tryb „premium dla wszystkich” (config premium.free) daje dodatki każdemu zalogowanemu graczowi.
 */
final class Premium
{
    /** Cennik: klucz => [kwota w groszach, dni]. */
    public const PLANS = [
        'week' => [500, 7],
        'month' => [1500, 30],
        'quarter' => [3000, 90],
        'half' => [5500, 180],
        'year' => [10000, 365],
    ];

    /** Najmniejsza wpłata (cegiełka) w groszach. */
    public const MIN_SUPPORT = 500;

    /** Największa wpłata online w groszach (ochrona przed pomyłką w kwocie). */
    public const MAX_SUPPORT = 100000;

    /** Premium za darmo dla wszystkich zalogowanych (config/premium.php); wpłaty są wtedy tylko wsparciem. */
    public static function free(): bool
    {
        return (bool) config('premium.free', false);
    }

    /** @return array<string, string> klucz => nazwa planu */
    public static function labels(): array
    {
        return [
            'week' => __('Week'),
            'month' => __('Month'),
            'quarter' => __('Quarter'),
            'half' => __('Half a year'),
            'year' => __('Year'),
        ];
    }

    /** Dni premium za wpłatę: najwyższa opcja cennika, na którą wystarcza kwoty (0, gdy mniej niż tydzień). */
    public static function daysFor(int $amount): int
    {
        $days = 0;
        foreach (self::PLANS as [$price, $planDays]) {
            if ($amount >= $price) {
                $days = max($days, $planDays);
            }
        }

        return $days;
    }

    /**
     * Czy konto ma premium: Admin zawsze, poza tym data ważności w przyszłości albo rola Premium nadana
     * ręcznie w panelu bez daty (premium bezterminowe).
     */
    public static function isActive(?User $user, ?CarbonInterface $at = null): bool
    {
        if (! $user) {
            return false;
        }

        if (self::free()) {
            return true;
        }

        if ($user->hasRole(RoleName::Admin->value)) {
            return true;
        }

        if ($user->premium_until !== null) {
            return $user->premium_until->greaterThan($at ?? now());
        }

        return $user->hasRole(RoleName::Premium->value);
    }

    /**
     * Przedłuża premium o podaną liczbę dni (od dziś albo od końca obecnego premium) i nadaje rolę Premium.
     * Zwraca nową datę wygaśnięcia.
     */
    public static function extend(User $user, int $days): CarbonInterface
    {
        $from = $user->premium_until?->isFuture() ? $user->premium_until->copy() : now();

        return self::setUntil($user, $from->addDays($days));
    }

    /**
     * Premium na start (config premium.welcome_days, domyślnie 14 dni) dla nowego konta po potwierdzeniu adresu e-mail.
     * Konto, które już kiedyś miało premium, nic nie dostaje. Zwraca datę wygaśnięcia albo null.
     */
    public static function welcome(User $user): ?CarbonInterface
    {
        $days = (int) config('premium.welcome_days', 0);

        if ($days <= 0 || $user->premium_until !== null || $user->hasRole(RoleName::Premium->value)
            || ! Role::where('name', RoleName::Premium->value)->where('guard_name', 'web')->exists()) {
            return null;
        }

        $until = self::extend($user, $days);
        Audit::log('premium.welcome', $user, [], ['premium_until' => $until->toDateTimeString()], __('Premium for :days days on the start', ['days' => $days]));

        return $until;
    }

    /** Ustawia datę wygaśnięcia (np. ręcznie w panelu). Data w przeszłości odbiera premium. */
    public static function setUntil(User $user, ?CarbonInterface $until): ?CarbonInterface
    {
        $user->forceFill(['premium_until' => $until])->save();

        if ($until !== null && $until->isFuture()) {
            $user->assignRole(RoleName::Premium->value);
        } elseif ($user->hasRole(RoleName::Premium->value)) {
            $user->removeRole(RoleName::Premium->value);
        }

        return $until;
    }

    /**
     * Zdejmuje rolę Premium tym, którym minęła data (rola bez daty zostaje) (polecenie premium:expire w harmonogramie).
     * Zwraca liczbę kont.
     */
    public static function expire(): int
    {
        // Rola bez daty to premium bezterminowe nadane w panelu, więc jej nie zdejmujemy.
        $users = User::role(RoleName::Premium->value)
            ->whereNotNull('premium_until')
            ->where('premium_until', '<=', now())
            ->get();

        foreach ($users as $user) {
            $user->removeRole(RoleName::Premium->value);
            Audit::log('premium.expired', $user, ['premium_until' => $user->premium_until?->toDateTimeString()], []);
        }

        return $users->count();
    }
}
