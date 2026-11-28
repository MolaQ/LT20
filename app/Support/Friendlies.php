<?php

namespace App\Support;

use App\Enums\MatchdayStatus;
use App\Models\Friendly;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\PushMessage;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sparingi (mecze towarzyskie, 27.11.2026). Zasady:
 *  - sparing zajmuje wolny typ rozgrywek w kolejce: kto w tej kolejce gra we wszystkich czterech, nie może wyzwać
 *    ani przyjąć sparingu; każdy gra w swoim wolnym typie (pierwszym wolnym), z jego pytaniami i składem,
 *  - jeden sparing na gracza w kolejce, umawiany do pierwszego gwizdka; wyzwany musi przyjąć,
 *  - wyzwanie do gracza albo otwarty link (przyjmuje pierwszy, kto go otworzy i może zagrać),
 *  - wynik liczony jak pojedynek ligowy (ScoreMatchday): atak (typ, zawodnicy, pytania) minus obrona rywala,
 *  - tylko prestiż: bilans na profilu, bez punktów do tabel i Hall of Fame. Zawodnicy ze sparingu odpoczywają jak zwykle.
 */
final class Friendlies
{
    /** Barwy kafelka sparingu (klucz CompetitionColors): stonowany grafit, żeby nie udawał rozgrywek. */
    public const COLORS = 'league_8';

    /** Najbliższa kolejka, na którą można umówić sparing (otwarta do typowania). */
    public static function nextMatchday(): ?Matchday
    {
        $season = Season::current();

        return $season ? Matchday::where('season_id', $season->id)
            ->where('status', MatchdayStatus::Planned->value)
            ->where('is_draft', false)
            ->whereNotNull('kickoff_at')
            ->where('kickoff_at', '>', now())
            ->orderBy('kickoff_at')
            ->get()
            ->first(fn (Matchday $m) => $m->isOpenForTips() && $m->isFilled()) : null;
    }

    /**
     * Wolne typy rozgrywek gracza w kolejce (bez tych, w których gra w rozgrywkach sezonu).
     *
     * @return list<string>
     */
    public static function freeSets(int $userId, Matchday $matchday): array
    {
        $taken = array_map(fn ($type) => $type->value, PlayerCompetitions::entryTypes($userId, $matchday->season_id, $matchday->number));

        return array_values(array_diff(Questions::sets(), $taken));
    }

    /** Sparing gracza w kolejce, który czeka na odpowiedź albo jest przyjęty. */
    public static function activeFor(int $userId, Matchday $matchday): ?Friendly
    {
        return Friendly::of($userId)->where('matchday_id', $matchday->id)->whereIn('status', [Friendly::PENDING, Friendly::ACCEPTED])->first();
    }

    /** Przyjęty sparing gracza w kolejce (z rywalami). */
    public static function acceptedFor(int $userId, Matchday $matchday): ?Friendly
    {
        return Friendly::of($userId)->with(['challenger', 'opponent'])->where('matchday_id', $matchday->id)->where('status', Friendly::ACCEPTED)->first();
    }

    /** Typ rozgrywek przyjętego sparingu gracza w kolejce sezonu (numer kolejki = runda). */
    public static function setInRound(int $userId, int $seasonId, int $round): ?string
    {
        $friendly = Friendly::of($userId)->where('status', Friendly::ACCEPTED)
            ->whereHas('matchday', fn ($q) => $q->where('season_id', $seasonId)->where('number', $round))
            ->first();

        return $friendly?->setFor($userId);
    }

    /** Dlaczego gracz nie może teraz umówić sparingu w kolejce (null: może). */
    public static function blocker(User $user, Matchday $matchday, ?Friendly $except = null): ?string
    {
        if (! Players::canPlay($user)) {
            return __('You cannot play now.');
        }
        if ($matchday->is_draft || ! $matchday->isOpenForTips() || ! $matchday->isFilled()) {
            return __('Friendlies can be arranged only until the kick-off of an open matchday.');
        }
        if (self::freeSets($user->id, $matchday) === []) {
            return __('You play in all four competition types in this matchday, so there is no room for a friendly.');
        }
        $active = self::activeFor($user->id, $matchday);
        if ($active && $active->id !== $except?->id) {
            return __('You already have a friendly in this matchday.');
        }

        return null;
    }

    /**
     * Wyzwanie na sparing: do gracza albo otwarty link (bez rywala).
     *
     * @throws DomainException
     */
    public static function challenge(User $me, Matchday $matchday, ?User $opponent = null): Friendly
    {
        if ($reason = self::blocker($me, $matchday)) {
            throw new DomainException($reason);
        }

        if ($opponent) {
            if ($opponent->id === $me->id) {
                throw new DomainException(__('You cannot play a friendly against yourself.'));
            }
            if (self::blocker($opponent, $matchday)) {
                throw new DomainException(__(':team cannot play a friendly in this matchday.', ['team' => self::teamName($opponent)]));
            }
        }

        $friendly = Friendly::create([
            'matchday_id' => $matchday->id,
            'challenger_id' => $me->id,
            'opponent_id' => $opponent?->id,
            'token' => Str::random(32),
            'challenger_set' => self::freeSets($me->id, $matchday)[0],
            'status' => Friendly::PENDING,
        ]);

        if ($opponent && Push::enabled() && $opponent->wantsPush('friendly')) {
            $opponent->notify(new PushMessage(
                'Friendly challenge',
                ':team challenges you to a friendly in matchday :number. Accept it before kick-off!',
                route('friendlies'),
                'friendly-'.$friendly->id,
                [],
                ['team' => self::teamName($me), 'number' => $matchday->number],
            ));
        }

        return $friendly;
    }

    /**
     * Przyjęcie wyzwania (wyzwany albo pierwszy gracz z otwartego linku).
     *
     * @throws DomainException
     */
    public static function accept(Friendly $friendly, User $me): void
    {
        $matchday = $friendly->matchday;

        if ($friendly->status !== Friendly::PENDING || ! $matchday) {
            throw new DomainException(__('This friendly is no longer waiting for an answer.'));
        }
        if ($friendly->challenger_id === $me->id) {
            throw new DomainException(__('You cannot play a friendly against yourself.'));
        }
        if ($friendly->opponent_id !== null && $friendly->opponent_id !== $me->id) {
            throw new DomainException(__('This challenge is for another player.'));
        }
        if ($reason = self::blocker($me, $matchday, $friendly)) {
            throw new DomainException($reason);
        }

        $friendly->update([
            'opponent_id' => $me->id,
            'opponent_set' => self::freeSets($me->id, $matchday)[0],
            'status' => Friendly::ACCEPTED,
        ]);

        $challenger = $friendly->challenger;
        if ($challenger && Push::enabled() && $challenger->wantsPush('friendly')) {
            $challenger->notify(new PushMessage(
                'Friendly accepted',
                ':team accepted your friendly in matchday :number. Set your squad and questions!',
                route('tips'),
                'friendly-'.$friendly->id,
                [],
                ['team' => self::teamName($me), 'number' => $matchday->number],
            ));
        }
    }

    /** Wyzwany odmawia. */
    public static function decline(Friendly $friendly, User $me): void
    {
        if ($friendly->status === Friendly::PENDING && $friendly->opponent_id === $me->id) {
            $friendly->update(['status' => Friendly::DECLINED]);
        }
    }

    /** Wyzywający wycofuje wyzwanie, dopóki nikt go nie przyjął. */
    public static function cancel(Friendly $friendly, User $me): void
    {
        if ($friendly->status === Friendly::PENDING && $friendly->challenger_id === $me->id) {
            $friendly->update(['status' => Friendly::CANCELLED]);
        }
    }

    /**
     * Typy rozgrywek sparingów kolejki: klub (Squad::clubKey) => typ => 1 mecz (do rozliczenia wystawionych zawodników).
     *
     * @return array<string, array<string, int>>
     */
    public static function matchesOf(Matchday $matchday): array
    {
        $out = [];
        foreach (Friendly::where('matchday_id', $matchday->id)->where('status', Friendly::ACCEPTED)->get() as $friendly) {
            $out[Squad::clubKey($friendly->challenger_id, null)][$friendly->challenger_set] = 1;
            if ($friendly->opponent_id && $friendly->opponent_set) {
                $out[Squad::clubKey($friendly->opponent_id, null)][$friendly->opponent_set] = 1;
            }
        }

        return $out;
    }

    /**
     * Wyniki sparingów kolejki (z ScoreMatchday): jak pojedynek ligowy, każdy w swoim typie rozgrywek.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $lineups  klub => typ => dorobek zawodników (Squad::settle)
     * @param  array<int, array<int|string, array<string, bool|int>>>  $questionBonus  user_id => typ => bonus z pytań
     * @param  Collection<int, Tip>  $tips  typy graczy według user_id
     */
    public static function settle(Matchday $matchday, array $lineups, array $questionBonus, Collection $tips): int
    {
        $friendlies = Friendly::where('matchday_id', $matchday->id)->where('status', Friendly::ACCEPTED)->whereNotNull('opponent_id')->get();

        foreach ($friendlies as $friendly) {
            $sides = [];
            foreach ([[$friendly->challenger_id, $friendly->challenger_set], [(int) $friendly->opponent_id, (string) $friendly->opponent_set]] as [$userId, $set]) {
                $tip = $tips->get($userId);
                $points = $tip ? Scoring::tip($tip->lech_goals, $tip->opponent_goals, (int) $matchday->lech_goals, (int) $matchday->opponent_goals)['points'] : 0;
                $lineup = $lineups[Squad::clubKey($userId, null)][$set] ?? [];
                $questions = $questionBonus[$userId][$set] ?? [];
                $sides[] = [
                    'offense' => $points + ($lineup['attack'] ?? 0) + ($questions['attack'] ?? 0),
                    'defense' => ($lineup['defense'] ?? 0) + ($questions['defense'] ?? 0),
                    'own_goals' => $lineup['own_goals'] ?? 0,
                ];
            }

            [$a, $b] = $sides;
            $friendly->update([
                'challenger_goals' => Scoring::goals($a['offense'] + $b['own_goals'], $b['defense']),
                'opponent_goals' => Scoring::goals($b['offense'] + $a['own_goals'], $a['defense']),
                'settled_at' => now(),
            ]);
        }

        return $friendlies->count();
    }

    /**
     * Bilans sparingów gracza.
     *
     * @return array{played: int, won: int, drawn: int, lost: int}
     */
    public static function record(int $userId): array
    {
        $out = ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0];
        foreach (Friendly::of($userId)->whereNotNull('settled_at')->get() as $friendly) {
            [$mine, $theirs] = $friendly->scoreFor($userId) ?? [0, 0];
            $out['played']++;
            $out[$mine > $theirs ? 'won' : ($mine < $theirs ? 'lost' : 'drawn')]++;
        }

        return $out;
    }

    /** Nazwa drużyny gracza (albo jego nazwa). */
    public static function teamName(?User $user): string
    {
        return $user ? ($user->team_name ?: $user->name) : '';
    }

    /** Nazwa typu rozgrywek sparingu na kafelkach i w nagłówkach („Sparing z FC Arka”). */
    public static function label(Friendly $friendly, int $userId): string
    {
        $rival = $friendly->rivalOf($userId);

        return $rival ? __('Friendly vs :team', ['team' => self::teamName($rival)]) : __('Friendly');
    }
}
