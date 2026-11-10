<?php

namespace App\Support;

use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamScore;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\PushMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Powiadomienia push o kolejkach: typowanie otwarte, przypomnienie przed pierwszym gwizdkiem (tylko kto nie typował),
 * wynik z punktami gracza i „rywal już typował”. Każde idzie raz (znaczniki push_*_at na kolejce), tylko do graczy
 * z listy sezonu, którzy włączyli powiadomienia w przeglądarce i nie wyłączyli tego rodzaju (users.push_prefs).
 * Bez kluczy VAPID (config/webpush.php) nic nie jest wysyłane.
 */
final class Push
{
    /** Rodzaje powiadomień (klucze users.push_prefs). */
    public const KINDS = ['open', 'reminder', 'results', 'rival'];

    /** „Typowanie otwarte” najwyżej tyle dni przed meczem (dalsze kolejki czekają). */
    public const OPEN_DAYS = 7;

    /** Przypomnienie tyle minut przed pierwszym gwizdkiem. */
    public const REMINDER_MINUTES = 120;

    public static function enabled(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    /** Harmonogram (co 5 minut): otwarcie i przypomnienie dla najbliższej kolejki bieżącego sezonu. */
    public static function tick(): int
    {
        $season = Season::current();
        if (! self::enabled() || ! $season) {
            return 0;
        }

        $next = Matchday::where('season_id', $season->id)
            ->where('status', MatchdayStatus::Planned->value)
            ->whereNotNull('kickoff_at')
            ->where('kickoff_at', '>', now())
            ->orderBy('kickoff_at')
            ->first();

        $kickoff = $next?->kickoff_at;

        if (! $next || ! $kickoff || blank($next->opponent)) {
            return 0;
        }

        $sent = 0;

        if ($next->push_open_at === null && $kickoff->lte(now()->addDays(self::OPEN_DAYS))) {
            $next->forceFill(['push_open_at' => now()])->save();
            $sent += self::open($next);
        }

        if ($next->push_reminder_at === null && $kickoff->lte(now()->addMinutes(self::REMINDER_MINUTES))) {
            $next->forceFill(['push_reminder_at' => now()])->save();
            $sent += self::reminder($next);
        }

        return $sent;
    }

    public static function open(Matchday $matchday): int
    {
        return self::send(self::wanting(self::recipients($matchday)->get(), 'open'), fn () => new PushMessage(
            'Matchday :number: tipping is open',
            ':fixture, :date. Make your tip!',
            route('tips'),
            'matchday-'.$matchday->id,
            ['number' => $matchday->number],
            ['fixture' => $matchday->fixture, 'date' => $matchday->kickoff_at?->translatedFormat('D j.m, H:i') ?? ''],
        ));
    }

    public static function reminder(Matchday $matchday): int
    {
        $users = self::wanting(self::recipients($matchday)
            ->whereNotIn('id', Tip::where('matchday_id', $matchday->id)->select('user_id'))
            ->get(), 'reminder');

        return self::send($users, fn () => new PushMessage(
            'Last call: matchday :number',
            'Tipping closes at :time (:fixture). You have not tipped yet.',
            route('tips'),
            'matchday-'.$matchday->id,
            ['number' => $matchday->number],
            ['time' => $matchday->kickoff_at?->format('H:i') ?? '', 'fixture' => $matchday->fixture],
        ));
    }

    /** Po pierwszym przeliczeniu kolejki: wynik i punkty gracza (za typ i bonusy). */
    public static function results(Matchday $matchday): int
    {
        if (! self::enabled() || $matchday->push_results_at !== null || $matchday->lech_goals === null) {
            return 0;
        }

        $matchday->forceFill(['push_results_at' => now()])->save();
        $score = $matchday->is_home
            ? 'Lech Poznań '.$matchday->lech_goals.':'.$matchday->opponent_goals.' '.$matchday->opponent
            : $matchday->opponent.' '.$matchday->opponent_goals.':'.$matchday->lech_goals.' Lech Poznań';

        $users = self::wanting(self::recipients($matchday)->get(), 'results');
        $scores = TeamScore::where('matchday_id', $matchday->id)->whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id');

        return self::send($users, function (User $user) use ($matchday, $score, $scores) {
            $row = $scores->get($user->id);
            $points = $row ? (int) $row->tip_points : 0;
            $bonus = $row ? (int) $row->offense_bonus + (int) $row->defense_bonus : 0;

            return new PushMessage(
                'Matchday :number settled',
                $row && $row->has_tip ? ':score. Your tip: :points pts, bonus :bonus.' : ':score. You did not tip this time.',
                route('results'),
                'results-'.$matchday->id,
                ['number' => $matchday->number],
                ['score' => $score, 'points' => $points, 'bonus' => $bonus],
            );
        });
    }

    /** Pierwszy typ gracza w kolejce: jego rywal w lidze (gracz, nie bot), który jeszcze nie typował, dostaje sygnał. */
    public static function rivalTipped(User $user, Matchday $matchday): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $rival = LeagueRival::team($user, $matchday)?->user;

        if (! $rival instanceof User || Tip::where('matchday_id', $matchday->id)->where('user_id', $rival->id)->exists()) {
            return 0;
        }

        $users = self::wanting(self::recipients($matchday)->whereKey($rival->id)->get(), 'rival');

        return self::send($users, fn () => new PushMessage(
            'Your rival has tipped',
            ':team has already tipped matchday :number. Your turn!',
            route('tips'),
            'rival-'.$matchday->id,
            [],
            ['team' => $user->team_name ?: $user->name, 'number' => $matchday->number],
        ));
    }

    /**
     * Gracze z listy sezonu kolejki, z subskrypcją push.
     *
     * @return Builder<User>
     */
    private static function recipients(Matchday $matchday): Builder
    {
        return User::query()
            ->whereHas('pushSubscriptions')
            ->whereIn('id', SeasonTeam::where('season_id', $matchday->season_id)->whereNotNull('user_id')->select('user_id'));
    }

    /**
     * Tylko ci, którzy nie wyłączyli tego rodzaju (users.push_prefs, domyślnie wszystko włączone).
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private static function wanting(Collection $users, string $kind): Collection
    {
        return $users->filter(fn (User $user) => $user->wantsPush($kind))->values();
    }

    /**
     * Wysyła każdemu jego powiadomienie, w jego języku (kolejka zadań, więc strona nie czeka).
     *
     * @param  Collection<int, User>  $users
     * @param  callable(User): PushMessage  $message
     */
    private static function send(Collection $users, callable $message): int
    {
        foreach ($users as $user) {
            // Błąd powiadomienia nie może zatrzymać typowania ani przeliczenia: tylko trafia do logów.
            try {
                $user->notify($message($user)->locale($user->locale ?: config('app.locale')));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $users->count();
    }
}
