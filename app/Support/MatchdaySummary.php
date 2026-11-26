<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;

/**
 * Podsumowanie rozliczonej kolejki (Promocja → Podsumowanie kolejki): dane do artykułu i grafik po meczu.
 * Mokre szmaty (gracze przegrani z botem), odpadli z Ligi Legend, typy kibiców z procentami i odpowiedzi na pytania,
 * a z MatchdayGraphics: najlepsi strzelcy, „pod prąd” i wyścig o nagrody. Artykuł to HTML z białej listy NewsHtml,
 * zapisywany jako szkic aktualności do poprawki.
 */
final class MatchdaySummary
{
    /** Ile najczęstszych typów pokazujemy. */
    private const SCORES = 5;

    /**
     * Mokre szmaty: zespoły graczy, które w tej kolejce przegrały z botem (we wszystkich rozgrywkach).
     * Remis w pucharze rozstrzyga winner_entry_id.
     *
     * @return list<array{team: string, bot: string, score: string, competition: string}>
     */
    public static function wetRags(Matchday $matchday): array
    {
        $competitions = Competition::where('season_id', $matchday->season_id)->get()->keyBy('id');
        $fixtures = Fixture::whereIn('competition_id', $competitions->keys())->where('round', $matchday->number)
            ->whereNotNull('home_goals')->whereNotNull('away_goals')
            ->with(['home.seasonTeam.user:id,name,team_name', 'home.seasonTeam.bot:id,name', 'away.seasonTeam.user:id,name,team_name', 'away.seasonTeam.bot:id,name'])
            ->get();

        $out = [];
        foreach ($fixtures as $fixture) {
            $home = self::team($fixture->home);
            $away = self::team($fixture->away);
            if (! $home || ! $away) {
                continue;
            }

            $winner = $fixture->winner_entry_id
                ?? ($fixture->home_goals > $fixture->away_goals ? $fixture->home_entry_id : ($fixture->home_goals < $fixture->away_goals ? $fixture->away_entry_id : null));
            if ($winner === null) {
                continue;
            }

            // Przegrany człowiek, zwycięzca bot.
            [$loser, $bot] = (int) $winner === (int) $fixture->home_entry_id ? [$away, $home] : [$home, $away];
            if ($loser->user_id === null || $bot->bot_id === null) {
                continue;
            }

            $competition = $competitions->get($fixture->competition_id);
            $out[] = [
                'team' => $loser->name,
                'bot' => $bot->name,
                'score' => $fixture->home_goals.':'.$fixture->away_goals,
                'competition' => $competition instanceof Competition ? $competition->name : '',
            ];
        }

        return $out;
    }

    /**
     * Zespoły, które odpadły z Ligi Legend w tej kolejce (dwie porażki w jednej kolejce).
     *
     * @return list<string>
     */
    public static function legendsOut(Matchday $matchday): array
    {
        $ids = Competition::where('season_id', $matchday->season_id)->where('type', CompetitionType::Legends->value)->pluck('id');

        return array_values(CompetitionEntry::whereIn('competition_id', $ids)->where('eliminated_round', $matchday->number)
            ->with(['seasonTeam.user:id,name,team_name', 'seasonTeam.bot:id,name'])
            ->get()
            ->map(fn (CompetitionEntry $entry) => (string) self::team($entry)?->name)
            ->filter()
            ->all());
    }

    /**
     * Najczęstsze typy z procentem i informacją, czy trafiły.
     *
     * @return list<array{score: string, count: int, percent: int, hit: bool}>
     */
    public static function scores(Matchday $matchday): array
    {
        $tips = Tip::where('matchday_id', $matchday->id)->get(['lech_goals', 'opponent_goals']);
        $total = max(1, $tips->count());
        $real = $matchday->lech_goals.':'.$matchday->opponent_goals;

        return array_values($tips->countBy(fn (Tip $t) => $t->lech_goals.':'.$t->opponent_goals)
            ->sortDesc()
            ->take(self::SCORES)
            ->map(fn (int $count, string $score) => ['score' => (string) $score, 'count' => $count, 'percent' => (int) round($count * 100 / $total), 'hit' => $score === $real])
            ->all());
    }

    /**
     * Wynik meczu z perspektywy gospodarza („2:1” albo „1:2” na wyjeździe).
     */
    public static function score(Matchday $matchday): string
    {
        return $matchday->is_home
            ? $matchday->lech_goals.':'.$matchday->opponent_goals
            : $matchday->opponent_goals.':'.$matchday->lech_goals;
    }

    /**
     * Artykuł podsumowania: tytuł i treść w HTML (akapity, nagłówki, listy) do szkicu aktualności.
     *
     * @return array{title: string, body: string}
     */
    public static function article(Matchday $matchday): array
    {
        $e = fn (mixed $text): string => e(is_string($text) ? $text : '');
        $tips = MatchdayInsights::tips($matchday);
        $count = max(1, $tips['count']);
        $club = (string) config('club.short_name');
        $fixture = $matchday->fixture;
        $score = self::score($matchday);

        $title = (string) __('Matchday :n summary: :fixture :score', ['n' => $matchday->number, 'fixture' => $fixture, 'score' => $score]);
        $html = [];

        // Wynik i trafienia.
        $html[] = '<p>'.$e(trans_choice(':fixture ended :score. :tips player tipped this match: :exact hit the exact score and :outcome% got the outcome right.|:fixture ended :score. :tips players tipped this match: :exact hit the exact score and :outcome% got the outcome right.', (int) $tips['count'], [
            'fixture' => $fixture, 'score' => $score, 'tips' => $tips['count'], 'exact' => (int) $tips['exact_count'], 'outcome' => (int) $tips['outcome'],
        ])).'</p>';

        // Typy kibiców.
        $scores = self::scores($matchday);
        if ($scores !== []) {
            $html[] = '<h2>'.$e(__('How the fans tipped')).'</h2>';
            $lech = (int) round($tips['lech'] * 100 / $count);
            $draw = (int) round($tips['draw'] * 100 / $count);
            $html[] = '<p>'.$e(__(':lech% backed :club, :draw% a draw and :rival% :opponent.', [
                'lech' => $lech, 'club' => $club, 'draw' => $draw, 'rival' => max(0, 100 - $lech - $draw), 'opponent' => (string) $matchday->opponent,
            ])).'</p>';
            $items = array_map(fn (array $s) => '<li><strong>'.$e($s['score']).'</strong> – '.$s['percent'].'%'.($s['hit'] ? ' ✅' : '').'</li>', $scores);
            $html[] = '<ul>'.implode('', $items).'</ul>';
            $hit = collect($scores)->firstWhere('hit', true);
            $html[] = '<p>'.$e($hit
                ? __('The real score was among the most popular tips: :percent% saw it coming.', ['percent' => $hit['percent']])
                : __('The real score was not among the most popular tips. The fans did not see this one coming!')).'</p>';
        }

        // Pytania.
        $sets = MatchdayGraphics::questions($matchday);
        if ($sets !== []) {
            $html[] = '<h2>'.$e(__('Questions of the matchday')).'</h2>';
            foreach ($sets as $set => $questions) {
                $html[] = '<h3>'.$e(CompetitionType::tryFrom((string) $set)?->label() ?? $set).'</h3>';
                $items = [];
                foreach ($questions as $q) {
                    $answer = $q['correct'] === null ? '' : ($q['correct'] ? __('Yes') : __('No'));
                    $right = $q['yes'] === null || $q['correct'] === null ? null : ($q['correct'] ? $q['yes'] : 100 - $q['yes']);
                    $items[] = '<li>'.$e($q['text']).($answer !== '' ? ' <strong>'.$e($answer).'</strong>' : '')
                        .($right !== null ? ' – '.$e(__(':percent% answered right', ['percent' => $right])) : '').'</li>';
                }
                $html[] = '<ul>'.implode('', $items).'</ul>';
            }
        }

        // Najlepsi i pod prąd.
        $scorers = MatchdayGraphics::scorers($matchday);
        if ($scorers !== []) {
            $html[] = '<h2>'.$e(__('Best of the matchday')).'</h2>';
            $items = array_map(fn (array $s, int $i) => '<li>'.($i + 1).'. <strong>'.$e($s['name']).'</strong> – '.$e(trans_choice(':n goal|:n goals', $s['goals'], ['n' => $s['goals']])).'</li>', $scorers, array_keys($scorers));
            $html[] = '<ol>'.implode('', $items).'</ol>';
        }
        $against = MatchdayGraphics::against($matchday);
        if ($against) {
            $html[] = '<p>'.$e(__('Against the tide: only :percent% tipped :outcome, and :names got it right. Respect!', [
                'percent' => $against['percent'], 'outcome' => $against['outcome'], 'names' => implode(', ', $against['names']),
            ])).'</p>';
        }

        // Mokre szmaty.
        $rags = self::wetRags($matchday);
        $html[] = '<h2>'.$e(__('Wet rags')).'</h2>';
        if ($rags === []) {
            $html[] = '<p>'.$e(__('Nobody lost to a bot this time. The bots go home empty-handed!')).'</p>';
        } else {
            $html[] = '<p>'.$e(trans_choice('One team lost to a bot this matchday. Time to wring out the rag:|:n teams lost to a bot this matchday. Time to wring out the rags:', count($rags), ['n' => count($rags)])).'</p>';
            $items = array_map(fn (array $r) => '<li><strong>'.$e($r['team']).'</strong> – '.$e($r['bot']).' '.$e($r['score']).' <em>('.$e($r['competition']).')</em></li>', $rags);
            $html[] = '<ul>'.implode('', $items).'</ul>';
        }

        // Liga Legend.
        $out = self::legendsOut($matchday);
        if ($out !== []) {
            $html[] = '<h2>'.$e(__('Out of the Legends League')).'</h2>';
            $html[] = '<p>'.$e(__('Two defeats in one matchday and the adventure is over for: :names.', ['names' => implode(', ', $out)])).'</p>';
        }

        // Odznaki.
        $badges = Badges::earnedIn($matchday);
        if ($badges !== []) {
            $html[] = '<h2>'.$e(__('New badges')).'</h2>';
            $items = array_map(fn (array $b) => '<li><strong>'.$e($b['name']).'</strong> – '.$e($b['label']).'</li>', array_slice($badges, 0, 15));
            $html[] = '<ul>'.implode('', $items).'</ul>';
        }

        // Wyścig o nagrody.
        $season = $matchday->season;
        $leaders = $season instanceof Season ? MatchdayGraphics::awardLeaders($season) : [];
        if ($leaders !== []) {
            $html[] = '<h2>'.$e(__('Race for the awards')).'</h2>';
            $items = array_map(fn (array $l) => '<li>'.$e($l['label']).': <strong>'.$e($l['name']).'</strong> ('.$e($l['value']).')</li>', $leaders);
            $html[] = '<ul>'.implode('', $items).'</ul>';
        }

        return ['title' => $title, 'body' => NewsHtml::clean(implode("\n", $html))];
    }

    /** Zespół wpisu w rozgrywkach (relacje bez typów generycznych, więc sprawdzamy klasę). */
    private static function team(mixed $entry): ?SeasonTeam
    {
        $team = $entry instanceof CompetitionEntry ? $entry->seasonTeam : null;

        return $team instanceof SeasonTeam ? $team : null;
    }
}
