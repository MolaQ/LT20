<?php

namespace App\Enums;

/**
 * Miejsca na banery partnerów (rotator). Każde miejsce ma własne proporcje grafiki, które edytor
 * obrazka wymusza przy wysyłaniu, i minimalny rozmiar pliku.
 *  - Season: tło paska sezonu na stronie głównej (szeroki baner, jak baner sponsora sezonu).
 *  - Panel: prostokąt 300×250 w prawej kolumnie (na telefonie w menu „Więcej”).
 */
enum BannerSlot: string
{
    case Season = 'season';
    case Panel = 'panel';

    public function label(): string
    {
        return match ($this) {
            self::Season => __('Season bar on the home page'),
            self::Panel => __('Right column (300×250)'),
        };
    }

    /** Proporcje kadru dla x-ui.image-cropper. */
    public function aspect(): string
    {
        return match ($this) {
            self::Season => '24/5',
            self::Panel => '6/5',
        };
    }

    /** Szerokość wysyłanego pliku w pikselach. */
    public function width(): int
    {
        return match ($this) {
            self::Season => 1920,
            self::Panel => 600,
        };
    }

    /** Reguła rozmiaru grafiki w walidacji. */
    public function dimensions(): string
    {
        return match ($this) {
            self::Season => 'dimensions:min_width=1200,min_height=250,max_width=4000,max_height=2000',
            self::Panel => 'dimensions:min_width=300,min_height=250,max_width=2000,max_height=2000',
        };
    }
}
