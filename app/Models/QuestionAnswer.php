<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Odpowiedź gracza na pytanie kolejki: answer (tak/nie) i side (attack = bonus ataku, defense = bonus obrony).
 *
 * @property int $id
 * @property int $matchday_question_id
 * @property int $user_id
 * @property bool $answer
 * @property string $side
 */
class QuestionAnswer extends Model
{
    public const SIDES = ['attack', 'defense'];

    protected $fillable = ['matchday_question_id', 'user_id', 'answer', 'side'];

    protected function casts(): array
    {
        return ['answer' => 'boolean'];
    }

    /** @return BelongsTo<MatchdayQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(MatchdayQuestion::class, 'matchday_question_id');
    }
}
