<?php

namespace App\Http\Controllers;

use App\Actions\CancelInvoice;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CancelInvoiceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, CancelInvoice $cancel): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']]);

        $cancel->handle($invoice, $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$invoice->number} iptal edildi."]);

        return back();
    }
}
