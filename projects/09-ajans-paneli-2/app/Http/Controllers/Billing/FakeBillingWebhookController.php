<?php

namespace App\Http\Controllers\Billing;

use App\Actions\ActivateSubscription;
use App\Billing\FakeBillingGateway;
use App\Billing\Plan;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RenewSubscription;
use App\Models\Agency;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Sahte sağlayıcının bildirimi. Aboneliği AÇAN ve kartı DEĞİŞTİREN tek kapı burasıdır;
 * tarayıcının "ödeme tamam" dönüşü hiçbir şeyi değiştirmez (Bölüm 6'daki ders).
 */
class FakeBillingWebhookController extends Controller
{
    private const TOLERANCE_SECONDS = 300;

    public function __invoke(Request $request, ActivateSubscription $activate): Response
    {
        // 1. İmza + zaman damgası (tekrar oynatmaya karşı)
        $timestamp = (string) $request->header('X-Fake-Timestamp');
        abort_unless(hash_equals(FakeBillingGateway::sign($timestamp, $request->getContent()), (string) $request->header('X-Fake-Signature')), 403, 'Geçersiz imza.');
        abort_if(abs(now()->timestamp - (int) $timestamp) > self::TOLERANCE_SECONDS, 403, 'Bildirimin süresi geçmiş.');

        $payload = $request->json()->all();
        $agency = Agency::findOrFail($payload['agency_id'] ?? null);

        try {
            DB::transaction(function () use ($payload, $agency, $activate) {
                // 2. Aynı olay ikinci kez işlenmez (unique)
                DB::table('billing_events')->insert([
                    'provider' => 'fake',
                    'event_id' => $payload['event_id'],
                    'type' => $payload['type'],
                    'payload' => json_encode($payload),
                    'created_at' => now(),
                ]);

                if ($payload['type'] !== 'checkout.completed') {
                    return; // reddedilen kart: kayıt yeter, abonelik açılmaz
                }

                // 3. İşle
                if ($payload['purpose'] === 'subscribe') {
                    $activate->handle($agency, Plan::find($payload['plan']), 'fake', $payload['card_token'], $payload['card_last4'], (int) $payload['amount'], $payload['reference']);

                    return;
                }

                // Kart değişikliği: ödeme bekleyen bir abonelik varsa hemen yeniden denenir
                $subscription = $agency->subscription;
                if ($subscription !== null) {
                    $subscription->forceFill(['card_token' => $payload['card_token'], 'card_last4' => $payload['card_last4'], 'last_attempt_at' => null])->save();

                    if ($subscription->status === SubscriptionStatus::PastDue) {
                        DB::afterCommit(fn () => RenewSubscription::dispatch($subscription));
                    }
                }
            });
        } catch (UniqueConstraintViolationException) {
            return response('Bu bildirim daha önce işlendi.', 200);
        }

        return response('OK', 200);
    }
}
