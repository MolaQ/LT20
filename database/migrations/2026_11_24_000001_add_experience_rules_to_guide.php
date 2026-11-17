<?php

use App\Models\GuideItem;
use App\Support\Guide;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Zasady gry edytowane w panelu (guide_items): dopisuje pozycje „Doświadczenie” i „Forma” po pucharach europejskich
 * i poprawia remis w Pucharze Polski (najpierw doświadczenie), jeśli admin nie zmieniał tej pozycji.
 * Bez zasad w tabeli strona i tak pokazuje nowe teksty domyślne z Support\Guide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guide_items') || ! GuideItem::where('kind', 'rules')->exists()) {
            return;
        }

        $pl = function (string $key): string {
            $text = __($key, [], 'pl');

            return is_string($text) ? $text : $key;
        };

        GuideItem::where('kind', 'rules')
            ->where('body', $pl('The favourite plays on the left and the winner takes the better seed of the pair. A draw goes to the team whose tip was saved first (every save gets its own number; bots and missing tips come last, then the higher seed), shown as “after penalties”.'))
            ->update(['body' => $pl('The favourite plays on the left and the winner takes the better seed of the pair. A draw goes to the team whose fielded players have more experience in the club, then to the team whose tip was saved first (every save gets its own number; bots and missing tips come last, then the higher seed), shown as “after penalties”.')]);

        $items = Guide::experienceRules('pl');
        if (GuideItem::where('kind', 'rules')->where('title', $items[0][0])->exists()) {
            return;
        }

        // Po pucharach europejskich (albo na końcu): kolejne pozycje przesuwamy niżej.
        $after = GuideItem::where('kind', 'rules')->where('title', $pl('European cups'))->value('position')
            ?? (int) GuideItem::where('kind', 'rules')->max('position');
        GuideItem::where('kind', 'rules')->where('position', '>', $after)->increment('position', count($items));

        foreach ($items as $i => [$title, $body]) {
            GuideItem::create(['kind' => 'rules', 'position' => $after + $i + 1, 'title' => $title, 'body' => $body]);
        }
    }

    public function down(): void
    {
        // Treści zostają: admin może je zmienić albo usunąć w panelu.
    }
};
