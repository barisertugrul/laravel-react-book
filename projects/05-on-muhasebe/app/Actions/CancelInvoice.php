<?php

namespace App\Actions;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

/**
 * Faturalar silinmez, iptal edilir (bkz. "Güvenlik incelemesi").
 * İptal edilen fatura numarasıyla birlikte kayıtlarda kalır, ama bakiyelere ve raporlara girmez.
 */
class CancelInvoice
{
    public function handle(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'Bu fatura zaten iptal edilmiş.']);
        }

        if ($invoice->paid_total > 0) {
            throw ValidationException::withMessages([
                'reason' => 'Ödemesi olan fatura iptal edilemez. Önce iade (ters) kaydı oluşturun.',
            ]);
        }

        $invoice->update(['cancelled_at' => now(), 'cancel_reason' => $reason]);

        return $invoice;
    }
}
