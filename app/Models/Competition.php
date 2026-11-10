<?php

namespace App\Models;

use App\Enums\CompetitionType;
use App\Enums\League;
use App\Support\Backyard;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Rozgrywki w ramach sezonu (na razie: jedna liga poziomów 1-10).
 *
 * @property int $season_id
 * @property CompetitionType $type
 * @property int|null $tier
 * @property int $division numer ligi podwórkowej (App\Support\Backyard: 1 = 1.1, 5 = 2.1 …), 0 dla pozostałych
 * @property string $name
 * @property int|null $sponsor_id
 */
class Competition extends Model
{
    protected $fillable = ['season_id', 'type', 'tier', 'division', 'name', 'sponsor_id'];

    protected function casts(): array
    {
        return [
            'type' => CompetitionType::class,
            'tier' => 'integer',
            'division' => 'integer',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /** Liga nowej Ligi podwórkowej (przebudowa 2.0): typ League, poziom 11, numer ligi w division. */
    public function isBackyard(): bool
    {
        return $this->type === CompetitionType::League && $this->tier === League::Podworkowa->value;
    }

    /** Poziom ligi do porównań (1-10, podwórkowa 11, także stara w systemie szwajcarskim) albo null dla pucharów. */
    public function leagueTier(): ?int
    {
        return match ($this->type) {
            CompetitionType::League => $this->tier,
            CompetitionType::Swiss => League::Podworkowa->value,
            default => null,
        };
    }

    /** Trofeum do zdobycia (klucz z HallOfFame::trophies()), np. league_3, cup, legends. */
    public function trophyKey(): string
    {
        return match ($this->type) {
            CompetitionType::League => 'league_'.$this->tier,
            CompetitionType::Swiss => 'league_'.League::Podworkowa->value,
            default => $this->type->value,
        };
    }

    /**
     * Stały, czytelny klucz rozgrywek w adresach (taki sam w każdym sezonie, więc da się porównać sezony):
     * ligi po nazwie poziomu (ekstraklasa, i-liga …), pozostałe po nazwie typu (puchar-polski, liga-legend …).
     * Każdy typ poza ligami występuje w sezonie raz, więc klucz jest unikalny w sezonie.
     */
    public function slug(): string
    {
        if ($this->isBackyard()) {
            return Str::slug(League::Podworkowa->label()).'-'.str_replace('.', '-', Backyard::label($this->division));
        }

        return $this->type === CompetitionType::League && $this->tier
            ? Str::slug(League::from((int) $this->tier)->label())
            : Str::slug($this->type->label());
    }

    /** Uczestnicy posortowani po rozstawieniu. */
    public function entries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class)->orderBy('seed');
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class)->orderBy('round')->orderBy('id');
    }

    /** Dla lig: odpowiadający poziom z enuma League. */
    protected function league(): Attribute
    {
        return Attribute::get(fn () => $this->tier ? League::tryFrom($this->tier) : null);
    }
}
