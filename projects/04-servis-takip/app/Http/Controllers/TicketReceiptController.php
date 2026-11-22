<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Cihaz teslim alınırken müşteriye verilen, takip QR kodlu servis fişi. */
class TicketReceiptController extends Controller
{
    public function __invoke(Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $qr = (new Writer(new ImageRenderer(new RendererStyle(140, 0), new SvgImageBackEnd())))
            ->writeString($ticket->tracking_url);

        $pdf = Pdf::loadView('pdf.ticket', [
            'ticket' => $ticket->load('customer'),
            // dompdf, SVG'yi data URI olarak verilen <img> içinde çizebilir
            'qr' => 'data:image/svg+xml;base64,'.base64_encode($qr),
            'business' => config('app.name'),
        ])->setPaper('a5');

        return $pdf->download("{$ticket->code}.pdf");
    }
}
