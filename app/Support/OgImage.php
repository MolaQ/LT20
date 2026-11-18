<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\HallOfFameAward;
use App\Models\Matchday;
use App\Models\Rival;
use App\Models\SeasonTeam;
use App\Models\TeamLike;
use App\Models\User;

/**
 * Obrazki rysowane na serwerze (kampania, 18.11.2026):
 * - podgląd linku do tabeli i do profilu drużyny (1200×630, og:image, App\Http\Controllers\OgImageController),
 * - grafika „przed meczem” z panelu kolejek (post 1080×1350 albo relacja 1080×1920).
 * Styl jak grafiki kampanii na Facebooku: granatowo-niebieski gradient (tabela w barwach rozgrywek), pasy, logo.
 */
final class OgImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const NAVY = '#061331';

    private const BLUE = '#1d4ed8';

    private const GOLD = '#fbbf24';

    private const SKY = '#93c5fd';

    /** Tabela rozgrywek po kolejce: 10 pierwszych miejsc ze strefami awansu i spadku. */
    public static function table(Competition $competition, int $round): string
    {
        $key = CompetitionColors::keyOf($competition);
        $colors = CompetitionColors::for($key);
        $rows = Standings::for($competition, null, max(1, $round));
        $count = $rows->count();
        $name = $competition->name ?: $competition->type->label();

        $g = (new Graphic(self::WIDTH, self::HEIGHT))
            ->gradient($colors['bg1'], $colors['bg2'])
            ->stripes(760)
            ->glow(1100, 640, 360, '#ffffff', 0.18);
        $ink = $colors['ink'];

        // Lewa kolumna: marka, nazwa rozgrywek, sezon i kolejka.
        self::brand($g, 56, 52, $ink);
        $nameSize = $g->fitSize($name, 54, 'Black', 400, 30);
        $g->text(56, 250, $name, $nameSize, $ink, 'Black', maxWidth: 400);
        $g->text(56, 300, __('Table').' · '.($competition->season->title ?? ''), 26, $ink, 'SemiBold', opacity: 0.85, maxWidth: 400);
        $g->text(56, 342, __('After matchday :number', ['number' => max(1, $round)]), 26, $ink, 'SemiBold', opacity: 0.85);
        self::cta($g, 56, 470, 0.9);

        // Prawa kolumna: tabela.
        [$x, $y, $w] = [500, 46, 644];
        $rowH = 48;
        $shown = $rows->take(10)->values();
        $g->rect($x, $y, $w, 50 + max(1, $shown->count()) * $rowH + 8, 22, '#0b1020', 0.62);
        $cols = ['#' => [$x + 46, 'right'], __('Team') => [$x + 66, 'left'], __('M') => [$x + 432, 'center'], __('Goals') => [$x + 512, 'center'], __('Pts') => [$x + $w - 30, 'right']];
        foreach ($cols as $label => [$cx, $align]) {
            $g->text($cx, $y + 33, mb_strtoupper($label), 15, '#cbd5e1', 'Bold', $align);
        }
        foreach ($shown as $i => $row) {
            $top = $y + 50 + $i * $rowH;
            if ($i % 2 === 1) {
                $g->rect($x + 8, $top, $w - 16, $rowH, 10, '#ffffff', 0.05);
            }
            $zone = Standings::zone($competition, $i + 1, $count);
            $bar = match ($zone) {
                'up' => '#22c55e',
                'down' => '#ef4444',
                default => null,
            };
            if ($bar) {
                $g->rect($x + 10, $top + 9, 5, $rowH - 18, 2, $bar);
            }
            $base = $top + 32;
            $g->text($x + 46, $base, ($i + 1).'.', 22, '#94a3b8', 'SemiBold', 'right');
            $g->text($x + 66, $base, (string) $row['team']->name, 22, '#ffffff', 'Bold', maxWidth: 320);
            $g->text($x + 432, $base, (string) $row['played'], 22, '#e2e8f0', 'SemiBold', 'center');
            $g->text($x + 512, $base, $row['for'].':'.$row['against'], 22, '#e2e8f0', 'SemiBold', 'center');
            $g->text($x + $w - 30, $base, (string) $row['points'], 24, self::GOLD, 'Black', 'right');
        }
        if ($shown->isEmpty()) {
            $g->text($x + intdiv($w, 2), $y + 90, __('The table appears after the first matchday.'), 20, '#cbd5e1', 'SemiBold', 'center');
        }

        return $g->png();
    }

    /** Profil drużyny: nazwa, sezony, punkty Hall of Fame, trofea i polubienia. */
    public static function team(User $user): string
    {
        $name = $user->team_name ?: $user->name;
        $awards = HallOfFameAward::where('user_id', $user->id)->get(['points', 'trophy']);
        $stats = [
            [__('Seasons'), (string) SeasonTeam::where('user_id', $user->id)->count()],
            [__('Hall of Fame points'), HallOfFame::format((float) $awards->sum('points'))],
            [__('Trophies'), (string) $awards->whereNotNull('trophy')->count()],
            [__('Likes'), (string) TeamLike::where('user_id', $user->id)->count()],
        ];

        $g = self::canvas(self::WIDTH, self::HEIGHT, 700);
        self::brand($g, 56, 52, '#ffffff');
        $g->text(56, 214, mb_strtoupper(__('Team in #LechTYPER')), 20, '#bfdbfe', 'Bold');
        $size = $g->fitSize($name, 76, 'Black', 1088, 36);
        $g->text(56, 214 + 22 + $size, $name, $size, '#ffffff', 'Black', maxWidth: 1088);

        $tileW = 254;
        foreach ($stats as $i => [$label, $value]) {
            $tx = 56 + $i * ($tileW + 24);
            $g->rect($tx, 392, $tileW, 132, 22, '#ffffff', 0.12);
            $g->text($tx + 24, 462, $value, 46, $i === 1 ? self::GOLD : '#ffffff', 'Black', maxWidth: $tileW - 40);
            $g->text($tx + 24, 500, $label, 18, '#dbeafe', 'SemiBold', maxWidth: $tileW - 40);
        }
        $g->text(1144, 592, 'lechtyper.pl', 22, '#bfdbfe', 'Bold', 'right');

        return $g->png();
    }

    /** Grafika przed meczem do posta (1080×1350) albo relacji (1080×1920). */
    public static function matchday(Matchday $matchday, string $format = 'post'): string
    {
        $story = $format === 'story';
        [$w, $h] = [1080, $story ? 1920 : 1350];
        $g = self::canvas($w, $h, 620);
        $lech = (string) config('club.name', 'Lech Poznań');
        $rival = (string) ($matchday->opponent ?: __('Rival'));
        $sides = $matchday->is_home ? [[$lech, Rival::LECH_COLORS], [$rival, Rival::colorsFor($rival)]] : [[$rival, Rival::colorsFor($rival)], [$lech, Rival::LECH_COLORS]];

        $top = $story ? 200 : 80;
        self::brand($g, 80, $top, '#ffffff', 92);

        // Rozgrywki i termin.
        $y = $top + ($story ? 330 : 230);
        $tag = mb_strtoupper($matchday->competitionLabel() ?? __('Next match'));
        $g->text(540, $y, $tag, 28, '#bfdbfe', 'Bold', 'center', 920);
        $when = $matchday->kickoff_at?->translatedFormat('l, d.m · H:i');
        if ($when) {
            $g->text(540, $y + 50, $when, 30, '#ffffff', 'SemiBold', 'center', 920);
        }

        // Drużyny jedna pod drugą z paskiem barw.
        $y += $story ? 190 : 180;
        foreach ($sides as $i => [$team, $colors]) {
            $size = $g->fitSize(mb_strtoupper($team), 92, 'Black', 920, 44);
            $g->text(540, $y, mb_strtoupper($team), $size, '#ffffff', 'Black', 'center');
            self::colorBar($g, 540, $y + 22, 180, $colors);
            if ($i === 0) {
                $g->text(540, $y + 110, 'vs', 44, self::SKY, 'Black', 'center');
                $y += 220;
            }
        }

        // Tablica „? : ?” i pytanie.
        $y += $story ? 120 : 60;
        foreach ([-130, 130] as $dx) {
            $g->rect(540 + $dx - 75, $y, 150, 200, 18, '#111827');
            $g->rect(540 + $dx - 75, $y + 99, 150, 3, 0, '#000000');
            $g->text(540 + $dx, $y + 148, '?', 130, '#ffffff', 'Black', 'center');
        }
        $g->ellipse(540, $y + 78, 11, 11, self::SKY)->ellipse(540, $y + 122, 11, 11, self::SKY);
        $y += 290;
        $g->text(540, $y, __('How do you tip it?'), 50, self::GOLD, 'Black', 'center');
        $g->text(540, $y + 52, __('Write your score in the comments and save it in the game.'), 26, '#dbeafe', 'SemiBold', 'center', 920);

        // Stopka: przycisk z adresem i hashtag.
        self::cta($g, 80, $h - ($story ? 330 : 190), 1.0);
        $g->text($w - 80, $h - ($story ? 260 : 120), '#LECHTYPER', 30, self::GOLD, 'Black', 'right');

        return $g->png();
    }

    /** Tło kampanii: gradient, pasy od $stripesFrom i poświaty. */
    private static function canvas(int $w, int $h, int $stripesFrom): Graphic
    {
        return (new Graphic($w, $h))
            ->gradient(self::NAVY, self::BLUE)
            ->stripes($stripesFrom)
            ->glow($w - 60, $h - 40, (int) ($w * 0.42), '#60a5fa', 0.5)
            ->glow(0, 0, (int) ($w * 0.3), '#3b82f6', 0.3);
    }

    /** Logo z napisem LechTYPER. */
    private static function brand(Graphic $g, int $x, int $y, string $ink, int $size = 64): void
    {
        $g->logo($x, $y, $size);
        $font = (int) round($size * 0.62);
        $base = $y + intdiv($size, 2) + intdiv($font, 3);
        $lech = $g->text($x + $size + 16, $base, 'Lech', $font, $ink, 'Black');
        $g->text($x + $size + 16 + $lech, $base, 'TYPER', $font, $ink === '#ffffff' ? self::SKY : $ink, 'Black', opacity: $ink === '#ffffff' ? 1 : 0.7);
    }

    /** Biały przycisk „lechtyper.pl / ZAGRAJ ZA DARMO”. */
    private static function cta(Graphic $g, int $x, int $y, float $scale): void
    {
        $s = fn (int $v) => (int) round($v * $scale);
        $g->rect($x, $y, $s(330), $s(108), $s(24), '#ffffff');
        $g->text($x + $s(30), $y + $s(58), 'lechtyper.pl', $s(44), '#0b2f74', 'Black');
        $g->text($x + $s(32), $y + $s(88), mb_strtoupper(__('Play for free')), $s(18), '#475569', 'Bold');
    }

    /**
     * Pasek barw drużyny (równe pasy) pod nazwą.
     *
     * @param  array<int, string>  $colors
     */
    private static function colorBar(Graphic $g, int $cx, int $y, int $width, array $colors): void
    {
        $colors = array_values($colors) ?: ['#94a3b8'];
        $part = intdiv($width, count($colors));
        $g->rect($cx - intdiv($width, 2) - 3, $y - 3, $part * count($colors) + 6, 18, 9, '#ffffff', 0.35);
        foreach ($colors as $i => $color) {
            $g->rect($cx - intdiv($width, 2) + $i * $part, $y, $part, 12, 0, $color);
        }
    }
}
