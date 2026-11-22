<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        $invoice->load(['contact', 'lines']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'vatBreakdown' => $invoice->lines->groupBy('vat_rate')->map->sum('vat')->sortKeys(),
            'business' => config('app.name'),
            'money' => fn (int $cents) => Money::format($cents),
        ])->download("{$invoice->number}.pdf");
    }
}
