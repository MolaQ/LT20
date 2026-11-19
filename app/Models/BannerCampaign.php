<?php

namespace App\Models;

use App\Enums\BannerSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kampania partnera w rotatorze banerów: grafika z linkiem i krótkim tekstem w jednym z miejsc strony.
 * Banery zmieniają się same co kilka sekund (bez przeładowania), częściej te z wyższą wagą.
 * Partner widzi swoje wyświetlenia i kliknięcia pod prywatnym linkiem (stats_token).
 *
 * @property int $id
 * @property string $name
 * @property BannerSlot $slot
 * @property string|null $title
 * @property string $url
 * @property string $image_path
 * @property int $weight
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 * @property string $stats_token
 */
class BannerCampaign extends Model
{
    protected $fillable = ['name', 'slot', 'title', 'url', 'image_path', 'weight', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'slot' => BannerSlot::class,
            'weight' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BannerCampaign $campaign): void {
            $campaign->stats_token ??= Str::random(40);
        });
    }

    /** @return HasMany<BannerStat, $this> */
    public function stats(): HasMany
    {
        return $this->hasMany(BannerStat::class);
    }

    /**
     * Kampanie, które teraz się wyświetlają: włączone i w terminie (daty włącznie).
     *
     * @param  Builder<BannerCampaign>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    /**
     * Banery miejsca w losowej kolejności ważonej wagą (cięższe częściej pierwsze), pusta lista
     * przed migracją tabeli.
     *
     * @return Collection<int, BannerCampaign>
     */
    public static function forSlot(BannerSlot $slot): Collection
    {
        if (! Schema::hasTable('banner_campaigns')) {
            return new Collection;
        }

        // Losowanie ważone bez powtórzeń (Efraimidis–Spirakis): klucz = u^(1/waga), sortowanie malejąco.
        return static::live()->where('slot', $slot->value)->get()
            ->sortByDesc(fn (BannerCampaign $c) => (mt_rand(1, mt_getrandmax()) / mt_getrandmax()) ** (1 / max(1, $c->weight)))
            ->values();
    }

    public function imageUrl(): string
    {
        return asset('storage/'.$this->image_path);
    }

    public function isLive(): bool
    {
        $today = now()->startOfDay();

        return $this->is_active
            && (! $this->starts_on || $this->starts_on->lte($today))
            && (! $this->ends_on || $this->ends_on->gte($today));
    }
}
