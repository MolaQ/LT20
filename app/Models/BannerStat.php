<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Dzienne liczniki kampanii banerowej: wyświetlenia (baner widoczny w co najmniej połowie przez sekundę)
 * i kliknięcia (przez przekierowanie /b/{id}). Dzień zapisany jako tekst Y-m-d.
 *
 * @property int $banner_campaign_id
 * @property string $day
 * @property int $views
 * @property int $clicks
 */
class BannerStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['banner_campaign_id', 'day', 'views', 'clicks'];

    protected function casts(): array
    {
        return ['views' => 'integer', 'clicks' => 'integer'];
    }

    /** @return BelongsTo<BannerCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BannerCampaign::class, 'banner_campaign_id');
    }

    /** Dolicza jedno wyświetlenie albo kliknięcie do dzisiejszego wiersza (tworzy go przy pierwszym). */
    public static function bump(int $campaignId, string $column): void
    {
        $day = now()->toDateString();
        $row = fn () => static::query()->where('banner_campaign_id', $campaignId)->where('day', $day);

        if ($row()->increment($column) > 0) {
            return;
        }

        try {
            static::create(['banner_campaign_id' => $campaignId, 'day' => $day, $column => 1]);
        } catch (UniqueConstraintViolationException) {
            // Równoległe żądanie utworzyło wiersz chwilę wcześniej.
            $row()->increment($column);
        }
    }
}
