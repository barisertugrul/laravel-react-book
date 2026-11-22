<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Bölüm 1'deki QR kodu üretimini tekrar kullanılabilir bir sınıfa taşıdık. */
class QrCode
{
    public static function svg(string $content, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd(),
        ));

        return $writer->writeString($content);
    }
}
