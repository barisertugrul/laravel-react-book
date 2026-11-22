<?php

namespace App\Http\Controllers;

use App\Actions\RecordPayment;
use App\Http\Requests\PaymentRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvoicePaymentController extends Controller
{
    public function __invoke(PaymentRequest $request, Invoice $invoice, RecordPayment $record): RedirectResponse
    {
        $record->handle(
            $invoice,
            $request->amountInCents(),
            $request->validated('paid_at'),
            $request->validated('method'),
            $request->user(),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ödeme kaydedildi.']);

        return back();
    }
}
