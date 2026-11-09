<?php

namespace App\Actions\Seasons;

use App\Enums\CompetitionType;
use App\Enums\League;
use App\Enums\SeasonStatus;
use App\Models\Bot;
use App\Models\Competition;
use App\Models\FinalStanding;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Support\Audit;
use App\Support\Backyard;
use App\Support\Players;
use App\Support\Promotion;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Lista przedsezonowa nowego sezonu z tabel końcowych poprzedniego (regulamin, punkt 9):
 * boty na koniec tabel, awanse i spadki po 4 w ligach 1-10, w Lidze podwórkowej zwycięzca w górę i po jednym spadkowiczu
 * do każdej ligi pod spodem (App\Support\Backyard), wypychanie botów pod ludzi (App\Support\Promotion::applyTree).
 * Boty za pełnymi ligami (SeasonTeam::listSize, co najmniej 520) nie wchodzą na listę i pauzują (bots.is_active = false),
 * ale zostają w puli dla miejsc zbanowanych graczy. Zespół gracza, który nie może już grać (ban, nieaktywny), zostaje na swoim
 * miejscu jako bot. Nowi gracze dochodzą potem przyciskiem "Dodaj nowych graczy" (na koniec podwórkowej).
 * Każde miejsce pamięta, z którego miejsca poprzedniego sezonu powstało (previous_id).
 */
class BuildListFromPrevious
{
    /**
     * @return array{teams: int, players: int, bots: int}
     *
     * @throws DomainException
     */
    public function handle(Season $season): array
    {
        if ($season->status !== SeasonStatus::Draft) {
            throw new DomainException(__('Only a draft season can be changed.'));
        }

        if (SeasonTeam::where('season_id', $season->id)->exists()) {
            throw new DomainException(__('The list already exists. Clear it first to build it again.'));
        }

        $previous = self::previousSeason($season);

        if (! $previous) {
            throw new DomainException(__('There is no finished previous season with final standings.'));
        }

        // Tabele końcowe lig 1-10 i podwórkowej: poziom => zespoły w kolejności miejsc.
        $competitions = Competition::where('season_id', $previous->id)
            ->whereIn('type', [CompetitionType::League->value, CompetitionType::Swiss->value])
            ->get()
            ->keyBy('id');

        $standings = FinalStanding::with('seasonTeam:id,user_id,bot_id')
            ->whereIn('competition_id', $competitions->keys())
            ->orderBy('place')
            ->get();

        $eligible = Players::eligible()->pluck('id')->flip();
        $leagues = self::leagueTree($competitions->all());
        $old = [];
        /** @var array<array-key, list<array{id: int, human: bool}>> $teams klucz ligi (treeKey) => tabela końcowa */
        $teams = [];

        foreach ($standings as $row) {
            $team = $row->seasonTeam;
            $old[$team->id] = $team;
            $teams[self::treeKey($competitions[$row->competition_id])][] = ['id' => (int) $team->id, 'human' => $team->user_id !== null && isset($eligible[$team->user_id])];
        }

        foreach (array_keys($leagues) as $key) {
            $leagues[$key]['teams'] = $teams[$key] ?? [];
        }

        foreach (League::cases() as $league) {
            if ($league->isTop() && count($teams[(string) $league->value] ?? []) !== League::SIZE) {
                throw new DomainException(__('The final standings of :league are incomplete.', ['league' => $league->label()]));
            }
        }

        $order = Promotion::applyTree($leagues);

        // Przejście ze starej podwórkowej (jedna tabela) na ligi po 10: spadkowicze z C klasy po jednym do lig 1.1–1.4.
        $legacy = League::Podworkowa->value;
        if (isset($order[$legacy])) {
            $order[$legacy] = self::spreadRelegated($order[$legacy], isset($teams[(string) League::CKlasa->value]) ? Promotion::MOVES : 0);
        }

        // Lista w pełnych ligach po 10 (SeasonTeam::listSize): boty za tą granicą pauzują.
        $humans = 0;
        foreach ($teams as $rows) {
            $humans += count(array_filter($rows, fn (array $t) => $t['human']));
        }
        $size = SeasonTeam::listSize($humans);

        return DB::transaction(function () use ($season, $previous, $order, $old, $eligible, $size): array {
            // Wolne boty z puli dla miejsc graczy, którzy już nie grają.
            $usedBots = collect($old)->pluck('bot_id')->filter()->all();
            $freeBots = Bot::whereNotIn('id', $usedBots)->bestFirst()->pluck('id')->all();

            $now = now();
            $rows = [];
            $position = 0;
            $players = 0;

            foreach ($order as $ids) {
                foreach ($ids as $id) {
                    $team = $old[$id];
                    $human = $team->user_id !== null && isset($eligible[$team->user_id]);

                    // Boty są na końcu listy (Promotion), więc odcięcie dotyczy tylko botów.
                    if (! $human && $position >= $size) {
                        continue;
                    }

                    $players += (int) $human;

                    $rows[] = [
                        'season_id' => $season->id,
                        'position' => ++$position,
                        'user_id' => $human ? $team->user_id : null,
                        'bot_id' => $human ? null : ($team->bot_id ?? array_shift($freeBots)),
                        'previous_id' => $team->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                SeasonTeam::insert($chunk);
            }

            Bot::syncActive($season->id);

            Audit::log(
                'season_list.built',
                null,
                [],
                ['players' => $players, 'bots' => count($rows) - $players, 'from' => $previous->title],
                $season->title,
            );

            return ['teams' => count($rows), 'players' => $players, 'bots' => count($rows) - $players];
        });
    }

    /**
     * Drzewo lig poprzedniego sezonu do awansów (Promotion::applyTree), w kolejności listy: ligi 1-10 (po 4 w górę i w dół),
     * stara podwórkowa w jednej tabeli (4 w górę) albo nowe ligi podwórkowe (zwycięzca w górę, Backyard::parent).
     *
     * @param  array<int, Competition>  $competitions
     * @return array<array-key, array{parent: string|null, up: int, teams: array<int, array{id: int, human: bool}>}>
     */
    public static function leagueTree(array $competitions): array
    {
        usort($competitions, fn (Competition $a, Competition $b) => [$a->leagueTier(), $a->division] <=> [$b->leagueTier(), $b->division]);

        $leagues = [];
        foreach ($competitions as $competition) {
            $tier = (int) $competition->leagueTier();

            if ($competition->isBackyard()) {
                $parent = Backyard::parent($competition->division);
                $leagues[self::treeKey($competition)] = [
                    'parent' => $parent !== null ? 'p'.$parent : (string) League::CKlasa->value,
                    'up' => Backyard::PROMOTED,
                    'teams' => [],
                ];
            } else {
                $leagues[self::treeKey($competition)] = ['parent' => $tier > 1 ? (string) ($tier - 1) : null, 'up' => Promotion::MOVES, 'teams' => []];
            }
        }

        return $leagues;
    }

    /**
     * Pierwsze $count zespołów (spadkowicze z C klasy) na początek kolejnych lig po 10, reszta w kolejności.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public static function spreadRelegated(array $ids, int $count): array
    {
        $relegated = array_slice($ids, 0, $count);
        $rest = array_slice($ids, $count);
        $result = [];

        foreach ($relegated as $id) {
            $result[] = $id;
            array_push($result, ...array_splice($rest, 0, League::SIZE - 1));
        }

        return array_merge($result, $rest);
    }

    /** Klucz ligi w drzewie: '1'..'10' (ligi), '11' (stara podwórkowa), 'p1', 'p2' … (nowe ligi podwórkowe). */
    public static function treeKey(Competition $competition): string
    {
        return $competition->isBackyard() ? 'p'.$competition->division : (string) $competition->leagueTier();
    }

    /** Ostatni zakończony sezon przed tym, który ma tabele końcowe. */
    public static function previousSeason(Season $season): ?Season
    {
        return Season::where('status', SeasonStatus::Finished->value)
            ->where('number', '<', $season->number)
            ->whereHas('competitions', fn ($q) => $q->whereIn('id', FinalStanding::select('competition_id')))
            ->orderByDesc('number')
            ->first();
    }
}
