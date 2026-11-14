<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pytanie tak/nie z banku, z którego admin układa pytania kolejki.
 *
 * @property int $id
 * @property string $text
 * @property bool $is_active
 * @property int $times_used
 * @property string|null $category
 */
class QuestionTemplate extends Model
{
    protected $fillable = ['text', 'category', 'is_active', 'times_used'];

    /** Podpowiedzi kategorii w panelu (admin może wpisać też własną). */
    public const CATEGORIES = [
        'Bramki', 'Strzały', 'Posiadanie piłki', 'Podania', 'Rzuty rożne', 'Stałe fragmenty',
        'Kartki', 'Faule', 'Bramkarz', 'Zmiany', 'Inne',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'times_used' => 'integer'];
    }
}
