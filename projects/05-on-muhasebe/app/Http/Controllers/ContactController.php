<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = Contact::query()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('tax_number', 'like', "%{$term}%")))
            // Bakiyeyi her cari için ayrı sorguyla değil, alt sorgularla tek seferde hesapla
            ->withSum(['invoices as invoiced' => fn ($q) => $q->whereNull('cancelled_at')], 'total')
            ->withSum(['invoices as paid' => fn ($q) => $q->whereNull('cancelled_at')], 'paid_total')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                ...$c->only(['id', 'name', 'tax_number', 'phone', 'email']),
                'type' => $c->type,
                'balance' => (int) $c->invoiced - (int) $c->paid,
            ]);

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => $request->only(['type', 'q']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('contacts/form', ['types' => $this->types()]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = Contact::create($request->validated());

        return to_route('contacts.show', $contact);
    }

    /** Cari hesap ekstresi: faturalar ve ödemeler, tarih sırasıyla ve yürüyen bakiyeyle */
    public function show(Contact $contact): Response
    {
        $invoices = $contact->invoices()->whereNull('cancelled_at')->get();
        $payments = $contact->payments()->with('invoice:id,number')->get();

        $entries = collect()
            ->concat($invoices->map(fn ($i) => [
                'date' => $i->issue_date->toDateString(),
                'kind' => 'invoice',
                'description' => "{$i->type->label()} faturası {$i->number}",
                'invoice_id' => $i->id,
                'debit' => $i->total,   // bakiyeyi artırır
                'credit' => 0,
            ]))
            ->concat($payments->map(fn ($p) => [
                'date' => $p->paid_at->toDateString(),
                'kind' => 'payment',
                'description' => "{$p->method->label()} ödeme ({$p->invoice->number})",
                'invoice_id' => $p->invoice_id,
                'debit' => 0,
                'credit' => $p->amount, // bakiyeyi azaltır
            ]))
            // Aynı gün önce fatura, sonra ödeme
            ->sortBy([['date', 'asc'], ['kind', 'desc']])
            ->values();

        // Yürüyen bakiye: her satırda o ana kadarki bakiye
        $balance = 0;
        $entries = $entries->map(function (array $e) use (&$balance) {
            $balance += $e['debit'] - $e['credit'];

            return [...$e, 'balance' => $balance];
        });

        return Inertia::render('contacts/show', [
            'contact' => [...$contact->toArray(), 'type_label' => $contact->type->label()],
            'entries' => $entries,
            'balance' => $balance,
        ]);
    }

    public function edit(Contact $contact): Response
    {
        return Inertia::render('contacts/form', ['contact' => $contact, 'types' => $this->types()]);
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return to_route('contacts.show', $contact);
    }

    private function types(): array
    {
        return array_map(fn (ContactType $t) => ['value' => $t->value, 'label' => $t->label()], ContactType::cases());
    }
}
