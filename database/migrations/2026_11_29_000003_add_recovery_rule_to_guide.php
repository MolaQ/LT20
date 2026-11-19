<?php

use App\Models\GuideItem;
use App\Support\Guide;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Zasady gry edytowane w panelu (guide_items): dopisuje pozycję „Regeneracja” po „Formie” (albo na końcu).
 * Bez zasad w tabeli strona i tak pokazuje nowy tekst domyślny z Support\Guide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guide_items') || ! GuideItem::where('kind', 'rules')->exists()) {
            return;
        }

        [[$title, $body]] = Guide::recoveryRules('pl');
        if (GuideItem::where('kind', 'rules')->where('title', $title)->exists()) {
            return;
        }

        $form = __('Form', [], 'pl');
        $after = GuideItem::where('kind', 'rules')->where('title', is_string($form) ? $form : 'Form')->value('position')
            ?? (int) GuideItem::where('kind', 'rules')->max('position');
        GuideItem::where('kind', 'rules')->where('position', '>', $after)->increment('position');

        GuideItem::create(['kind' => 'rules', 'position' => $after + 1, 'title' => $title, 'body' => $body]);
    }

    public function down(): void
    {
        // Treść zostaje: admin może ją zmienić albo usunąć w panelu.
    }
};
