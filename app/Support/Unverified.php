<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Konta bez potwierdzonego maila (decyzja MolaQ z 25.11.2026):
 *  - po REMIND_AFTER_HOURS godzinach od rejestracji jedno przypomnienie z nowym kodem,
 *  - po DELETE_AFTER_DAYS dniach porzucone konto znika (harmonogram „users:unverified”),
 *  - admin może skasować takie konto od razu (np. gdy mail wrócił jako zły adres), strona Użytkownicy.
 * Kasujemy tylko konta „puste”: bez potwierdzenia, bez roli, bez miejsca na liście, bez typów i wpłat.
 */
final class Unverified
{
    public const REMIND_AFTER_HOURS = 24;

    public const DELETE_AFTER_DAYS = 7;

    /**
     * Konta, które można bezpiecznie skasować (niezależnie od wieku).
     *
     * @return Builder<User>
     */
    public static function deletable(): Builder
    {
        return User::query()
            ->whereNull('email_verified_at')
            ->whereDoesntHave('roles')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('season_teams')->whereColumn('season_teams.user_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tips')->whereColumn('tips.user_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('payments')->whereColumn('payments.user_id', 'users.id'));
    }

    /** Przypomnienie z nowym kodem dla kont starszych niż doba (raz na konto). Zwraca liczbę wysłanych maili. */
    public static function remind(): int
    {
        $users = User::whereNull('email_verified_at')
            ->whereNull('verification_reminded_at')
            ->where('created_at', '<=', now()->subHours(self::REMIND_AFTER_HOURS))
            ->where('created_at', '>', now()->subDays(self::DELETE_AFTER_DAYS))
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            // Znacznik przed wysyłką: błąd poczty nie powinien zasypać skrzynki przypomnieniami co godzinę.
            $user->forceFill(['verification_reminded_at' => now()])->save();

            try {
                $user->sendEmailVerificationNotification(reminder: true);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    /** Kasuje porzucone konta starsze niż DELETE_AFTER_DAYS dni. Zwraca liczbę skasowanych. */
    public static function cleanup(): int
    {
        return self::delete(self::deletable()->where('created_at', '<=', now()->subDays(self::DELETE_AFTER_DAYS))->get(), __('Abandoned sign-up'));
    }

    /**
     * Kasuje wskazane konta, o ile nadal są „puste” (deletable), z wpisem w dzienniku zmian.
     *
     * @param  iterable<int, User>  $users
     */
    public static function delete(iterable $users, string $reason): int
    {
        $allowed = self::deletable()->pluck('id')->flip();
        $deleted = 0;

        foreach ($users as $user) {
            if (! isset($allowed[$user->id])) {
                continue;
            }

            Audit::log('user.deleted', null, ['name' => $user->name, 'email' => $user->email, 'created_at' => $user->created_at?->format('Y-m-d H:i')], [], $reason);
            $user->delete();
            $deleted++;
        }

        return $deleted;
    }
}
