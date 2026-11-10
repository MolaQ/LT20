<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pytanie kolejki (najwyżej 5). correct: prawidłowa odpowiedź po meczu (null = jeszcze nie wpisana),
 * voided: pytanie unieważnione (nie liczy się nikomu, nie zeruje bonusu).
 *
 * @property int $id
 * @property int $matchday_id
 * @property int|null $question_template_id
 * @property int $position
 * @property string $text
 * @property bool|null $correct
 * @property bool $voided
 */
class MatchdayQuestion extends Model
{
    protected $fillable = ['matchday_id', 'question_template_id', 'position', 'text', 'correct', 'voided'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'correct' => 'boolean', 'voided' => 'boolean'];
    }

    /** @return BelongsTo<Matchday, $this> */
    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    /** @return HasMany<QuestionAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class);
    }
}
