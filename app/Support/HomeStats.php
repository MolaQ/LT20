<?php

namespace App\Support;

use App\Enums\MatchdayStatus;
use App\Models\DesignatedPlayer;
use App\Models\Fixture;
use App\Models\HallOfFameAward;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Models\Season;
use App\Models\Tip;
use App\Models\User;

/**
 * Liczby na stronę główną (16.11.2026): puls bieżącej kolejki (odświeżany na żywo), ciekawostki sezonu
 * (celność typów, najskuteczniejsi) i liczby serwisu, które zmieniają się rzadko (na dole strony).
 * Liczymy tylko typy graczy (boty nie mają wierszy w tips).
 */
final class HomeStats
{
    /**
     * Puls kolejki: ile typów, składów, odpowiedzi i pojedynków, kto typował ostatnio (bez wyników typów).
     *
     * @return array{tips: int, players: int, percent: int, squads: int, answers: int, duels: int, recent: array<int, string>}
     */
    public static function live(Matchday $matchday): array
    {
        $tips = Tip::where('matchday_id', $matchday->id)->count();
        $players = Players::eligible()->count();
        $questionIds = MatchdayQuestion::where('matchday_id', $matchday->id)->pluck('id');

        return [
            'tips' => $tips,
            'players' => $players,
            'percent' => $players > 0 ? min(100, (int) round($tips * 100 / $players)) : 0,
            'squads' => DesignatedPlayer::where('matchday_id', $matchday->id)->whereNotNull('user_id')->distinct()->count('user_id'),
            'answers' => $questionIds->isEmpty() ? 0 : QuestionAnswer::whereIn('matchday_question_id', $questionIds)->count(),
            'duels' => Fixture::where('round', $matchday->number)
                ->whereHas('competition', fn ($q) => $q->where('season_id', $matchday->season_id))->count(),
            // Ostatnio typujący (nazwy zespołów), od najświeższego.
            'recent' => Tip::where('matchday_id', $matchday->id)
                ->join('users', 'users.id', '=', 'tips.user_id')
                ->whereNotNull('users.team_name')
                ->orderByDesc('tips.saved_at')
                ->limit(6)
                ->pluck('users.team_name')
                ->all(),
        ];
    }

    /**
     * Ciekawostki sezonu z rozegranych kolejek: celność typów i najskuteczniejsi typerzy.
     *
     * @return array{matchdays: int, tips: int, outcome: int, exact: int, exact_count: int,
     *   top: array<int, array{team: string, user_id: int, tips: int, exact: int, outcome: int, percent: int}>}|null
     */
    public static function season(Season $season): ?array
    {
        $played = Matchday::where('season_id', $season->id)->where('status', MatchdayStatus::Played->value)
            ->whereNotNull('lech_goals')->whereNotNull('opponent_goals')->get(['id', 'lech_goals', 'opponent_goals'])->keyBy('id');

        if ($played->isEmpty()) {
            return null;
        }

        $rows = Tip::whereIn('matchday_id', $played->keys())->get(['matchday_id', 'user_id', 'lech_goals', 'opponent_goals'])
            ->map(function (Tip $tip) use ($played) {
                $real = $played[$tip->matchday_id];

                return ['user_id' => $tip->user_id] + Scoring::tip($tip->lech_goals, $tip->opponent_goals, (int) $real->lech_goals, (int) $real->opponent_goals);
            });

        if ($rows->isEmpty()) {
            return null;
        }

        $byUser = $rows->groupBy('user_id')->map(fn ($tips, $userId) => [
            'user_id' => (int) $userId,
            'tips' => $tips->count(),
            'exact' => $tips->where('exact', true)->count(),
            'outcome' => $tips->where('outcome', true)->count(),
            'points' => $tips->sum('points'),
        ]);

        $top = $byUser->sortBy([['exact', 'desc'], ['outcome', 'desc'], ['points', 'desc'], ['user_id', 'asc']])->take(5)->values();
        $names = User::whereIn('id', $top->pluck('user_id'))->pluck('team_name', 'id');

        return [
            'matchdays' => $played->count(),
            'tips' => $rows->count(),
            'outcome' => (int) round($rows->where('outcome', true)->count() * 100 / $rows->count()),
            'exact' => (int) round($rows->where('exact', true)->count() * 100 / $rows->count()),
            'exact_count' => $rows->where('exact', true)->count(),
            'top' => $top->map(fn (array $row) => [
                'team' => (string) ($names[$row['user_id']] ?? '—'),
                'user_id' => $row['user_id'],
                'tips' => $row['tips'],
                'exact' => $row['exact'],
                'outcome' => $row['outcome'],
                'percent' => (int) round($row['outcome'] * 100 / max(1, $row['tips'])),
            ])->all(),
        ];
    }

    /**
     * Liczby serwisu (zmieniają się rzadko): gracze, oddane typy, rozegrane pojedynki, zdobyte trofea.
     *
     * @return array<int, array{icon: string, value: int, label: string}>
     */
    public static function numbers(): array
    {
        return [
            ['icon' => 'users', 'value' => Players::eligible()->count(), 'label' => __('Players')],
            ['icon' => 'pencil-square', 'value' => Tip::count(), 'label' => __('Tips given')],
            ['icon' => 'bolt', 'value' => Fixture::whereNotNull('home_goals')->count(), 'label' => __('Duels played')],
            ['icon' => 'trophy', 'value' => HallOfFameAward::whereNotNull('trophy')->count(), 'label' => __('Trophies won')],
        ];
    }
}
