<?php

namespace App\Actions\Seasons;

use App\Models\Bot;
use App\Models\Season;
use App\Models\SeasonTeam;
use Illuminate\Support\Facades\DB;

/**
 * Dopełnia listę sezonu botami z puli do pełnych lig (SeasonTeam::listSize: co najmniej 520, potem do pełnej dziesiątki,
 * przebudowa 2.0), więc Liga podwórkowa zawsze gra w ligach po 10. Pozostałe boty pauzują (is_active).
 *
 * Kolejność: najpierw ewentualne luki w numeracji (1..max), potem koniec listy.
 * Boty idą od najlepszych (Bot::bestFirst), więc wynik jest powtarzalny. Gracze zostają tam,
 * gdzie są, a pierwsze 512 miejsc to uczestnicy Pucharu Polski.
 * Używane przy zatwierdzaniu sezonu i przyciskiem "Uzupełnij botami".
 *
 * @return int ile botów dodano
 */
class FillTeamListWithBots
{
    public function handle(Season $season): int
    {
        return DB::transaction(function () use ($season): int {
            $used = SeasonTeam::where('season_id', $season->id)->whereNotNull('bot_id')->select('bot_id');
            $count = SeasonTeam::where('season_id', $season->id)->count();
            $needed = SeasonTeam::listSize($count) - $count;

            $bots = $needed > 0 ? Bot::query()->whereNotIn('id', $used)->bestFirst()->limit($needed)->get() : collect();

            if ($bots->isEmpty()) {
                Bot::syncActive($season->id);

                return 0;
            }

            $taken = SeasonTeam::where('season_id', $season->id)->pluck('position')->all();
            $takenSet = array_flip($taken);
            $max = $taken === [] ? 0 : max($taken);

            // Wolne numery w środku listy.
            $gaps = [];
            for ($position = 1; $position <= $max; $position++) {
                if (! isset($takenSet[$position])) {
                    $gaps[] = $position;
                }
            }

            $next = $max + 1;
            $now = now();
            $rows = [];

            foreach ($bots as $index => $bot) {
                $rows[] = [
                    'season_id' => $season->id,
                    'position' => $gaps[$index] ?? $next++,
                    'user_id' => null,
                    'bot_id' => $bot->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                SeasonTeam::insert($chunk);
            }

            Bot::syncActive($season->id);

            return count($rows);
        });
    }
}
