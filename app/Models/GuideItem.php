<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pozycja „Zasad gry”, „FAQ” albo tekst karty „Postaw kawkę” (coffee, jedna pozycja) edytowana w panelu (App\Support\Guide).
 *
 * @property string $kind rules | faq | coffee
 * @property int $position
 * @property string $title
 * @property string $body
 */
class GuideItem extends Model
{
    public const KINDS = ['rules', 'faq', 'coffee'];

    protected $fillable = ['kind', 'position', 'title', 'body'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
