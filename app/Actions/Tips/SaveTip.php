<?php

namespace App\Actions\Tips;

use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Support\Players;
use App\Support\Push;
use App\Support\TipRules;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Zapisuje typ gracza (wynik meczu Lecha).
 *
 * Zasady:
 *  - typować może tylko gracz (rola, bez bana), będący na liście zespołów aktywnego sezonu,
 *  - tylko do godziny pierwszego gwizdka (Matchday::isOpenForTips),
 *  - saved_at zmienia się wyłącznie, gdy typ faktycznie się zmienił
 *    (ponowne zapisanie tych samych danych nie psuje pierwszeństwa w remisie pucharu),
 *  - każdy zapis zmiany dostaje kolejny numer z dziennika tip_saves (save_order): liczy się ostatni zapis,
 *    a mniejszy numer wygrywa remisy tam, gdzie rozstrzyga czas typu (puchary, Liga Legend),
 *  - $auto: 0:0 zapisane samo przy otwarciu strony typowania (tylko najbliższa kolejka). Nie nadpisuje typu gracza,
 *    nie wysyła rywalowi powiadomienia, a pierwszy zapis gracza zdejmuje flagę is_auto.
 */
class SaveTip
{
    /**
     * @throws DomainException gdy typowanie jest niedozwolone lub dane są błędne
     */
    public function handle(User $user, Matchday $matchday, mixed $lechGoals, mixed $opponentGoals, bool $auto = false): Tip
    {
        if (! Players::canPlay($user)) {
            throw new DomainException(__('Your account cannot take part in the game.'));
        }

        $season = $matchday->season;

        if (! $season || $season->status !== SeasonStatus::Active) {
            throw new DomainException(__('Tipping is possible only in the active season.'));
        }

        if (! $matchday->isOpenForTips()) {
            throw new DomainException(__('Tipping for this matchday is closed.'));
        }

        $onList = SeasonTeam::where('season_id', $season->id)->where('user_id', $user->id)->exists();

        if (! $onList) {
            throw new DomainException(__('You are not on the team list of this season.'));
        }

        $lech = TipRules::goals($lechGoals);
        $opponent = TipRules::goals($opponentGoals);

        if ($lech === null || $opponent === null) {
            throw new DomainException(__('Enter the score as numbers from 0 to :max.', ['max' => TipRules::MAX_GOALS]));
        }

        $notify = false;

        $saved = DB::transaction(function () use ($user, $matchday, $lech, $opponent, $auto, &$notify) {
            $tip = Tip::where('matchday_id', $matchday->id)->where('user_id', $user->id)->lockForUpdate()->first();
            // Pierwszy własny typ gracza (nowy albo zamiast automatycznego 0:0): rywal dostaje powiadomienie.
            $notify = ! $auto && (! $tip || $tip->is_auto);

            // Automat nie rusza istniejącego typu.
            if ($tip && $auto) {
                return $tip;
            }

            $changed = ! $tip
                || $tip->lech_goals !== $lech
                || $tip->opponent_goals !== $opponent;

            if (! $changed) {
                // Gracz sam potwierdził automatyczne 0:0: od teraz to jego typ.
                if ($tip->is_auto) {
                    $tip->update(['is_auto' => false]);
                }

                return $tip;
            }

            $tip ??= new Tip(['matchday_id' => $matchday->id, 'user_id' => $user->id]);
            $savedAt = now();
            $order = DB::table('tip_saves')->insertGetId([
                'matchday_id' => $matchday->id,
                'user_id' => $user->id,
                'created_at' => $savedAt->format('Y-m-d H:i:s.u'),
            ]);

            $tip->fill(['lech_goals' => $lech, 'opponent_goals' => $opponent, 'saved_at' => $savedAt, 'save_order' => $order, 'is_auto' => $auto])->save();

            return $tip;
        });

        // Pierwszy własny typ w kolejce: rywal w lidze dostaje powiadomienie, że już typowaliśmy (App\Support\Push).
        if ($notify) {
            Push::rivalTipped($user, $matchday);
        }

        return $saved;
    }
}
