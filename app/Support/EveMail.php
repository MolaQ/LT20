<?php

namespace App\Support;

use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\EveReminder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Przypomnienie e-mailem przed meczem (28.11.2026), dla graczy, do których nie dociera push „dzień przed”
 * (bez subskrypcji w przeglądarce, z wyłączonym tym rodzajem albo gdy serwer nie ma kluczy VAPID).
 * Termin wybiera gracz w ustawieniach powiadomień (users.email_reminder_hours: HOURS, 0 = wyłączone).
 * Tylko gracze z listy sezonu, z potwierdzonym adresem, z prawem gry i bez typu w kolejce. Każdy dostaje najwyżej
 * jeden mail na kolejkę (matchday_email_reminders), z harmonogramu co 5 minut.
 */
final class EveMail
{
    /** Terminy do wyboru: godziny przed pierwszym gwizdkiem. */
    public const HOURS = [48, 24, 12, 6, 3];

    public const DEFAULT_HOURS = 24;

    public static function tick(): int
    {
        $season = Season::current();
        if (! $season) {
            return 0;
        }

        $next = Matchday::where('season_id', $season->id)
            ->where('status', MatchdayStatus::Planned->value)
            ->where('is_draft', false)
            ->whereNotNull('kickoff_at')
            ->where('kickoff_at', '>', now())
            ->orderBy('kickoff_at')
            ->first();

        // Ostatnie dwie godziny przed meczem należą do ostatniego wezwania (push), mail już by nie pomógł.
        if (! $next || ! $next->kickoff_at || blank($next->opponent) || $next->kickoff_at->lte(now()->addMinutes(Push::REMINDER_MINUTES))) {
            return 0;
        }

        return self::send($next);
    }

    /**
     * Gracze, którym właśnie wypada termin maila przed kolejką (i jeszcze go nie dostali).
     *
     * @return Collection<int, User>
     */
    public static function due(Matchday $matchday): Collection
    {
        $hoursLeft = (int) ceil(now()->diffInMinutes($matchday->kickoff_at, false) / 60);

        return User::with(['roles', 'pushSubscriptions'])
            ->whereIn('id', SeasonTeam::where('season_id', $matchday->season_id)->whereNotNull('user_id')->select('user_id'))
            ->whereNotIn('id', Tip::where('matchday_id', $matchday->id)->select('user_id'))
            ->whereNotIn('id', DB::table('matchday_email_reminders')->where('matchday_id', $matchday->id)->select('user_id'))
            ->whereNotNull('email_verified_at')
            ->where('email_reminder_hours', '>', 0)
            ->where('email_reminder_hours', '>=', $hoursLeft)
            ->get()
            ->filter(fn (User $user) => Players::canPlay($user) && ! self::getsPush($user))
            ->values();
    }

    /** Czy przypomnienie push dzień przed meczem i tak do gracza dotrze. */
    public static function getsPush(User $user): bool
    {
        return Push::enabled() && $user->pushSubscriptions->isNotEmpty() && $user->wantsPush('eve');
    }

    public static function send(Matchday $matchday): int
    {
        $users = self::due($matchday);
        foreach ($users as $user) {
            // Zapis przed wysyłką: przy błędzie poczty gracz nie dostanie tego samego maila co 5 minut.
            DB::table('matchday_email_reminders')->insertOrIgnore(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'sent_at' => now()]);
            try {
                $user->notify((new EveReminder($matchday))->locale($user->locale ?: config('app.locale')));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $users->count();
    }
}
