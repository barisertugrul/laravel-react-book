<?php

namespace App\Http\Controllers\Payments;

use App\Actions\CancelOrder;
use App\Actions\MarkOrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Payments\FakeGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Sahte sağlayıcıdan gelen ödeme bildirimi (webhook).
 * Siparişi "ödendi" yapan TEK kapı burasıdır.
 */
class FakeWebhookController extends Controller
{
    /** İmzalı ama 5 dakikadan eski istekler reddedilir (tekrar oynatma saldırısı) */
    private const TOLERANCE_SECONDS = 300;

    public function __invoke(Request $request, MarkOrderPaid $markPaid, CancelOrder $cancel): Response
    {
        // 1. İmza: bu isteği gerçekten anahtarı bilen sağlayıcı mı gönderdi?
        $timestamp = (string) $request->header('X-Fake-Timestamp');
        $expected = FakeGateway::sign($timestamp, $request->getContent());

        // hash_equals: karşılaştırma süresi, eşleşen karakter sayısına göre değişmez
        abort_unless(hash_equals($expected, (string) $request->header('X-Fake-Signature')), 403, 'Geçersiz imza.');
        abort_if(abs(now()->timestamp - (int) $timestamp) > self::TOLERANCE_SECONDS, 403, 'Bildirimin süresi geçmiş.');

        $payload = $request->json()->all();
        $order = Order::where('number', $payload['order'] ?? null)->firstOrFail();

        try {
            DB::transaction(function () use ($payload, $order, $markPaid, $cancel) {
                // 2. Tekrar koruması: aynı event_id ikinci kez kaydedilemez (unique)
                PaymentEvent::create([
                    'provider' => 'fake',
                    'event_id' => $payload['event_id'],
                    'order_id' => $order->id,
                    'type' => $payload['type'],
                    'payload' => $payload,
                ]);

                // 3. İşle
                match ($payload['type']) {
                    'payment.succeeded' => $markPaid->handle($order, (int) $payload['amount'], $payload['reference']),
                    'payment.failed' => $cancel->handle($order),
                };
            });
        } catch (UniqueConstraintViolationException) {
            return response('Bu bildirim daha önce işlendi.', 200);
        }

        // 200: sağlayıcıya "aldım" deriz; aksi halde aynı bildirimi tekrar tekrar gönderir
        return response('OK', 200);
    }
}
