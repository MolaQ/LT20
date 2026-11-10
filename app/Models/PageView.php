<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Jedna odsłona strony publicznej (zapisuje App\Http\Middleware\TrackPageView). Bez IP i bez ciasteczek:
 * visitor_hash zmienia się codziennie. Trzymamy ok. 13 miesięcy, żeby porównywać sezony.
 *
 * @property string $path
 * @property string $visitor_hash
 */
class PageView extends Model
{
    use Prunable;

    public const RETENTION_DAYS = 400;

    public const UPDATED_AT = null;

    protected $fillable = ['path', 'route_name', 'visitor_hash', 'referrer_host', 'device', 'locale'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
