<?php

namespace App\Support;

use App\Models\CompetitionEntry;
use App\Models\LechPlayer;
use App\Models\Matchday;
use App\Models\SeasonTeam;

/**
 * „Twoje składy w tej kolejce” na stronie głównej (18.11.2026): rozgrywki gracza w kolejce (nazwa i barwy jak w systemie),
 * limit zawodników i wystawieni z numerem, wychowankiem, formą (MatchReport) i doświadczeniem w klubie gracza.
 */
final class MySquads
{
    /**
     * @return list<array{set: string, name: string, key: string, max: int, players: list<array{number: int|null, name: string, academy: bool, form: float, experience: float}>}>
     */
    public static function for(int $userId, Matchday $matchday): array
    {
        $sets = PlayerCompetitions::types($userId, $matchday->season_id, $matchday->number);
        if ($sets === []) {
            return [];
        }

        $colors = PlayerCompetitions::colors($userId, $matchday->season_id);
        $names = [];
        $teamId = SeasonTeam::where('season_id', $matchday->season_id)->where('user_id', $userId)->value('id');
        if ($teamId) {
            foreach (CompetitionEntry::with('competition')->where('season_team_id', $teamId)->get() as $entry) {
                if ($entry->competition) {
                    $names[$entry->competition->type->scoreSet()->value] = (string) ($entry->competition->name ?: $entry->competition->type->label());
                }
            }
        }

        $picks = Squad::picks($userId, null, $matchday);
        $players = LechPlayer::whereIn('id', collect($picks)->flatten()->all())->get()->keyBy('id');
        $experience = Squad::clubExperience($userId, null);
        $form = MatchReport::formTotals();
        $max = Designation::value('max_players');

        $out = [];
        foreach ($sets as $set) {
            $list = [];
            foreach ($picks[$set->value] ?? [] as $id) {
                $player = $players->get($id);
                if ($player) {
                    $list[] = [
                        'number' => $player->number,
                        'name' => self::short($player->name),
                        'academy' => $player->is_academy,
                        'form' => (float) ($form[$id] ?? 0.0),
                        'experience' => (float) ($experience[$id] ?? 0.0),
                    ];
                }
            }
            $out[] = [
                'set' => $set->value,
                'name' => $names[$set->value] ?? (string) $set->scoreSetLabel(),
                'key' => $colors[$set->value] ?? 'league_1',
                'max' => $max,
                'players' => $list,
            ];
        }

        return $out;
    }

    /** „Bartosz Mrozek” => „B. Mrozek”, żeby nazwisko mieściło się w wąskiej kolumnie. */
    public static function short(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return count($parts) > 1 ? mb_substr($parts[0], 0, 1).'. '.implode(' ', array_slice($parts, 1)) : trim($name);
    }
}
