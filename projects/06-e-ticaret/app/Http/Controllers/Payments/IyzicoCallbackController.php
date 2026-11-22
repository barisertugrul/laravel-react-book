<?php

namespace App\Http\Controllers\Payments;

use App\Actions\CancelOrder;
use App\Actions\MarkOrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Payments\IyzicoGateway;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * iyzico, ödeme bitince müşterinin tarayıcısını bu adrese POST eder ve yalnızca bir token gönderir.
 * Tarayıcıdan gelen hiçbir bilgiye ("ödeme başarılı") güvenmeyiz: token ile sonucu
 * kendi sunucumuzdan iyzico'nun sunucusuna sorarız.
 */
class IyzicoCallbackController extends Controller
{
    public function __invoke(Request $request, IyzicoGateway $iyzico, MarkOrderPaid $markPaid, CancelOrder $cancel): RedirectResponse
    {
        $token = (string) $request->input('token');
        abort_if($token === '', 400);

        $result = $iyzico->retrieve($token);
        $order = Order::where('number', $result->getBasketId())->firstOrFail();

        DB::transaction(function () use ($token, $result, $order, $markPaid, $cancel) {
            $event = PaymentEvent::firstOrCreate(
                ['provider' => 'iyzico', 'event_id' => $token],
                ['order_id' => $order->id, 'type' => (string) $result->getPaymentStatus(), 'payload' => json_decode($result->getRawResult(), true)],
            );

            if (! $event->wasRecentlyCreated) {
                return; // sayfa yenilendi veya geri tuşuyla tekrar gönderildi
            }

            if ($result->getStatus() === 'success' && $result->getPaymentStatus() === 'SUCCESS') {
                $markPaid->handle($order, Money::parse($result->getPaidPrice()), (string) $result->getPaymentId());
            } else {
                $cancel->handle($order);
            }
        });

        return redirect()->route('checkout.result', $order);
    }
}
