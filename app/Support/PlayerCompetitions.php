<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\SeasonTeam;

/**
 * W jakich typach rozgrywek sezonu gra dany użytkownik (CompetitionType::scoreSet)?
 * Liga i Liga podwórkowa liczą się razem, więc obie zwracamy jako League.
 * Zespół, który odpadł z pucharu, pucharu europejskiego albo z Ligi Legend, już w nich nie gra
 * (w kolejce, w której odpadł, jeszcze tak).
 */
class PlayerCompetitions
{
    /** @return array<int, CompetitionType> w kolejności z enuma */
    public static function types(int $userId, int $seasonId, ?int $round = null): array
    {
        $alive = fn ($q) => $round === null
            ? $q->whereNull('eliminated_round')
            : $q->where(fn ($w) => $w->whereNull('eliminated_round')->orWhere('eliminated_round', '>=', $round));

        $present = Competition::where('season_id', $seasonId)
            ->whereHas('entries', fn ($q) => $alive($q)->whereHas('seasonTeam', fn ($t) => $t->where('user_id', $userId)))
            ->pluck('type')
            ->map(fn ($t) => ($t instanceof CompetitionType ? $t : CompetitionType::from($t))->scoreSet())
            ->all();

        return array_values(array_filter(CompetitionType::cases(), fn ($t) => in_array($t, $present, true)));
    }

    /**
     * Barwy typów rozgrywek gracza (klucz CompetitionColors): jego liga, Puchar Polski, jego puchar europejski, Liga Legend.
     *
     * @return array<string, string> typ rozgrywek (score_set) => klucz barw
     */
    public static function colors(int $userId, int $seasonId): array
    {
        $colors = [
            CompetitionType::League->value => 'league_1',
            CompetitionType::Cup->value => 'cup',
            CompetitionType::Champions->value => 'champions',
            CompetitionType::Legends->value => 'legends',
        ];
        $teamId = SeasonTeam::where('season_id', $seasonId)->where('user_id', $userId)->value('id');

        if ($teamId) {
            foreach (Competition::whereIn('id', CompetitionEntry::where('season_team_id', $teamId)->select('competition_id'))->get() as $competition) {
                $colors[$competition->type->scoreSet()->value] = $competition->trophyKey();
            }
        }

        return $colors;
    }
}
