<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    /**
     * @param  int  $amount  kuruş
     */
    public function handle(Invoice $invoice, int $amount, string $paidAt, string $method, User $by, ?string $note = null): Payment
    {
        // Aynı faturaya aynı anda iki ödeme girilirse kalan tutar kontrolü yanılmasın diye kilit
        return Cache::lock("invoice-payment:{$invoice->id}", 10)->block(5, function () use ($invoice, $amount, $paidAt, $method, $by, $note) {
            $invoice->refresh(); // kilit alındıktan sonra güncel kalan tutarı oku

            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages(['amount' => 'İptal edilmiş faturaya ödeme girilemez.']);
            }

            if ($amount > $invoice->remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Ödeme, kalan tutarı ('.Money::format($invoice->remaining).') aşamaz.',
                ]);
            }

            return DB::transaction(function () use ($invoice, $amount, $paidAt, $method, $by, $note) {
                $payment = $invoice->payments()->create([
                    'user_id' => $by->id,
                    'paid_at' => $paidAt,
                    'amount' => $amount,
                    'method' => $method,
                    'note' => $note,
                ]);

                // Tek bir UPDATE sorgusu: paid_total = paid_total + ?
                $invoice->increment('paid_total', $amount);

                return $payment;
            });
        });
    }
}
