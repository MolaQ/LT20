<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Odznaka gracza (App\Support\Badges): postęp i zdobyty stopień.
 *
 * @property int $id
 * @property int $user_id
 * @property string $badge klucz z Badges::definitions()
 * @property int $progress
 * @property int $tier 0 = jeszcze nie zdobyta, 1–3 = brąz, srebro, złoto
 * @property Carbon|null $earned_at
 * @property int|null $matchday_id kolejka, po której stopień wzrósł
 */
class UserBadge extends Model
{
    protected $fillable = ['user_id', 'badge', 'progress', 'tier', 'earned_at', 'matchday_id'];

    protected function casts(): array
    {
        return ['progress' => 'integer', 'tier' => 'integer', 'earned_at' => 'datetime', 'matchday_id' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
