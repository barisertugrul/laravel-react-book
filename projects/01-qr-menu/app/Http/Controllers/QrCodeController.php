<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('qr-code', [
            'menuUrl' => route('menu'),
            'svg' => $this->svg(),
        ]);
    }

    public function download()
    {
        return response($this->svg(1024), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="menu-qr.svg"',
        ]);
    }

    /** Menü adresini içeren QR kodu SVG olarak üretir. */
    private function svg(int $size = 320): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd(),
        ));

        return $writer->writeString(route('menu'));
    }
}
