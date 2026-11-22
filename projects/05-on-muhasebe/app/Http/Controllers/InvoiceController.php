<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvoice;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Http\Requests\InvoiceRequest;
use App\Models\Contact;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('invoices/index', [
            'invoices' => self::filtered($request)
                ->with('contact:id,name')
                ->latest('issue_date')->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only(['type', 'status', 'q']),
        ]);
    }

    /** Liste ve CSV dışa aktarma aynı filtreleri kullanır; sorgu tek yerde. */
    public static function filtered(Request $request): Builder
    {
        return Invoice::query()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('contact', fn ($q) => $q->where('name', 'like', "%{$term}%"))))
            ->when($request->query('status'), fn ($q, $status) => match ($status) {
                'overdue' => $q->overdue(),
                'outstanding' => $q->outstanding(),
                'paid' => $q->whereNull('cancelled_at')->whereColumn('paid_total', '>=', 'total'),
                'cancelled' => $q->whereNotNull('cancelled_at'),
                default => $q,
            });
    }

    public function create(Request $request): Response
    {
        $type = InvoiceType::tryFrom($request->query('type', 'sales')) ?? InvoiceType::Sales;

        return Inertia::render('invoices/create', [
            'type' => $type->value,
            'typeLabel' => $type->label(),
            'contacts' => Contact::where('type', $type === InvoiceType::Sales ? 'customer' : 'supplier')
                ->orderBy('name')->get(['id', 'name']),
            'defaults' => [
                'issue_date' => CarbonImmutable::today()->toDateString(),
                'due_date' => CarbonImmutable::today()->addDays(30)->toDateString(),
                'contact_id' => $request->integer('contact') ?: null,
            ],
        ]);
    }

    public function store(InvoiceRequest $request, CreateInvoice $create): RedirectResponse
    {
        $invoice = $create->handle($request->invoiceData(), $request->lines());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$invoice->number} oluşturuldu."]);

        return to_route('invoices.show', $invoice);
    }

    public function show(Invoice $invoice): Response
    {
        return Inertia::render('invoices/show', [
            'invoice' => $invoice->load(['contact', 'lines', 'payments.user:id,name']),
            'vatBreakdown' => $invoice->lines->groupBy('vat_rate')->map->sum('vat')->sortKeys(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }
}
