<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Enums\MatchdayStatus;
use App\Enums\PlayerPosition;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\DesignatedPlayer;
use App\Models\LechPlayer;
use App\Models\Season;
use App\Models\SeasonTeam;

/**
 * Kadra drużyny gracza na stronie zespołu (zakładka „Kadra”, 17.11.2026): dorobek zawodników wystawionych przez drużynę
 * w rozbiciu na sezon i typ rozgrywek. Strona sumuje wiersze w przeglądarce (filtry sezonu, rozgrywek i pozycji bez
 * zapytań do serwera). Liczą się tylko kolejki z wynikiem, więc skład bieżącej kolejki nie wychodzi przed gwizdkiem.
 */
class TeamSquad
{
    /** Kolumny sum w wierszu (ta sama kolejność co w rows). */
    public const SUMS = ['fielded', 'goals', 'assists', 'clean_sheets', 'attack', 'defense', 'yellow', 'red', 'experience'];

    /**
     * seasons: od bieżącego wstecz (bieżący zawsze, także bez wystawień), comps: rozgrywki drużyny w sezonie (typ => klucz barw
     * i nazwa), all: typy z całej historii z ogólną nazwą, players: kadra (wystawieni i pozostali dostępni), rows: sumy
     * [player, season, set, ...SUMS].
     *
     * @return array{seasons: array<int, array{id: int, label: string, current: bool}>, comps: array<int, array<string, array{key: string, label: string}>>, all: array<string, array{key: string, label: string}>, players: array<int, array<string, mixed>>, rows: array<int, array<int, int|float|string>>, formMatches: array<int, array{id: int, opponent: string, date: string|null}>}
     */
    public static function for(int $userId, ?Season $current): array
    {
        $picks = DesignatedPlayer::query()
            ->join('matchdays', 'matchdays.id', '=', 'designated_players.matchday_id')
            ->where('designated_players.user_id', $userId)
            ->where('designated_players.counted', true)
            ->where('designated_players.matches', '>', 0)
            ->where('matchdays.status', MatchdayStatus::Played->value)
            ->get(['designated_players.*', 'matchdays.season_id']);

        /** @var array<string, array{0: int, 1: int, 2: string}> $keys */
        $keys = [];
        /** @var array<string, array<int, int|float>> $sums */
        $sums = [];
        foreach ($picks as $pick) {
            $times = $pick->matches;
            $set = $pick->score_set->scoreSet()->value;
            $seasonId = (int) $pick->getAttribute('season_id');
            $key = $pick->lech_player_id.'|'.$seasonId.'|'.$set;
            $keys[$key] = [(int) $pick->lech_player_id, $seasonId, $set];
            $add = [
                $times,
                $pick->goals * $times,
                $pick->assists * $times,
                $pick->clean_sheet === true ? $times : 0,
                $pick->attack * $times,
                $pick->defense * $times,
                $pick->yellow * $times,
                (int) $pick->red * $times,
                $pick->experience * $times,
            ];
            $sums[$key] ??= array_fill(0, count(self::SUMS), 0);
            foreach ($add as $i => $value) {
                $sums[$key][$i] += $value;
            }
        }

        $rows = [];
        foreach ($keys as $key => [$playerId, $seasonId, $set]) {
            $values = $sums[$key];
            $values[8] = round((float) $values[8], 1);
            $rows[] = [$playerId, $seasonId, $set, ...$values];
        }

        // Sezony od bieżącego wstecz: te z wystawieniami i zawsze bieżący.
        $seasonIds = collect($rows)->pluck(1)->push($current?->id)->filter()->unique();
        $seasons = Season::whereIn('id', $seasonIds)->orderByDesc('number')->get()
            ->map(fn (Season $s) => ['id' => $s->id, 'label' => $s->title, 'current' => $s->id === $current?->id])
            ->values()->all();

        // Rozgrywki drużyny w każdym sezonie (nazwa i barwy jak w systemie), w kolejności typów.
        $comps = [];
        $order = array_flip(array_map(fn (CompetitionType $t) => $t->value, CompetitionType::cases()));
        foreach ($seasons as $season) {
            $teamId = SeasonTeam::where('season_id', $season['id'])->where('user_id', $userId)->value('id');
            $list = $teamId
                ? Competition::whereIn('id', CompetitionEntry::where('season_team_id', $teamId)->select('competition_id'))->get()
                : collect();
            $sets = [];
            foreach ($list->sortBy(fn (Competition $c) => $order[$c->type->scoreSet()->value]) as $competition) {
                $sets[$competition->type->scoreSet()->value] = ['key' => $competition->trophyKey(), 'label' => (string) $competition->name];
            }
            // Wystawienia bez wpisu w rozgrywkach (np. stare dane): ogólna nazwa typu.
            foreach (array_unique(array_column(array_filter($rows, fn (array $r) => $r[1] === $season['id']), 2)) as $set) {
                $sets[(string) $set] ??= self::generic(CompetitionType::from((string) $set));
            }
            $comps[$season['id']] = $sets;
        }

        $all = [];
        foreach (CompetitionType::cases() as $type) {
            if ($type === $type->scoreSet() && collect($rows)->contains(fn ($r) => $r[2] === $type->value)) {
                $all[$type->value] = self::generic($type);
            }
        }

        // Kadra: dostępni zawodnicy i każdy, kogo drużyna kiedyś wystawiła; forma z ostatnich meczów Lecha.
        $form = MatchReport::form();
        $squad = Squad::available();
        $extra = LechPlayer::whereIn('id', collect($rows)->pluck(0)->unique()->diff($squad->pluck('id')))->get();
        $posOrder = array_flip(PlayerPosition::values());
        $players = $squad->concat($extra)->map(fn (LechPlayer $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'number' => $p->number,
            'pos' => $p->position->value,
            'posShort' => $p->position->short(),
            'posRank' => $posOrder[$p->position->value] ?? 9,
            'academy' => (bool) $p->is_academy,
            'form' => $form['players'][$p->id]['total'] ?? 0.0,
            'series' => array_map(fn ($g) => $g === null ? null : [$g['experience'], $g['goals'], $g['assists']], $form['players'][$p->id]['series'] ?? array_fill(0, count($form['matches']), null)),
        ])->values()->all();

        return ['seasons' => $seasons, 'comps' => $comps, 'all' => $all, 'players' => $players, 'rows' => $rows, 'formMatches' => $form['matches']];
    }

    /** @return array{key: string, label: string} ogólna nazwa i barwy typu rozgrywek (widok „Wszystkie” sezony) */
    private static function generic(CompetitionType $type): array
    {
        return match ($type->scoreSet()) {
            CompetitionType::League => ['key' => 'league_1', 'label' => __('League')],
            CompetitionType::Champions => ['key' => 'champions', 'label' => __('European cups')],
            default => ['key' => $type->value, 'label' => $type->label()],
        };
    }
}
