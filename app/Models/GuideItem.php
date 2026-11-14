<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pozycja „Zasad gry” albo „FAQ” edytowana w panelu (App\Support\Guide).
 *
 * @property string $kind rules | faq
 * @property int $position
 * @property string $title
 * @property string $body
 */
class GuideItem extends Model
{
    public const KINDS = ['rules', 'faq'];

    protected $fillable = ['kind', 'position', 'title', 'body'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
