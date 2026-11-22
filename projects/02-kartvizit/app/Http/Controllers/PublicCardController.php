<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Support\QrCode;
use App\Support\VCard;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicCardController extends Controller
{
    public function show(Request $request, Card $card): Response
    {
        $isOwner = $request->user()?->id === $card->user_id;

        // Yayında olmayan kartı yalnızca sahibi önizleyebilir.
        abort_unless($card->is_published || $isOwner, 404);

        if (! $isOwner) {
            // Tek bir UPDATE sorgusu: view_count = view_count + 1
            $card->increment('view_count');
        }

        return Inertia::render('public-card', [
            'card' => $card->load('links'),
            'qrSvg' => QrCode::svg($card->public_url),
            'isOwner' => $isOwner,
        ]);
    }

    public function vcard(Card $card): HttpResponse
    {
        abort_unless($card->is_published, 404);

        return response(VCard::fromCard($card->load('links')), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$card->slug.'.vcf"',
        ]);
    }
}
