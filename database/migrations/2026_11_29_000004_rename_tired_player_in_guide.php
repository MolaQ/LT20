<?php

use App\Models\GuideItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Zasada „Regeneracja” w regulaminie (guide_items): „Zmęczony zawodnik:” to teraz „Prawie zregenerowany (na żółto):”,
 * tak samo jak w poradniku i na stronie typowania.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guide_items')) {
            return;
        }

        foreach (GuideItem::where('kind', 'rules')->where('body', 'like', '%Zmęczony zawodnik:%')->get() as $item) {
            $item->update(['body' => str_replace('Zmęczony zawodnik:', 'Prawie zregenerowany (na żółto):', (string) $item->body)]);
        }
    }

    public function down(): void
    {
        // Treść zostaje: admin może ją zmienić w panelu.
    }
};
