export type Option<T = string> = { value: T; label: string };

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type ContactType = 'customer' | 'supplier';
export type InvoiceType = 'sales' | 'purchase';
export type InvoiceStatus =
    | 'open'
    | 'partial'
    | 'paid'
    | 'overdue'
    | 'cancelled';
export type PaymentMethod = 'cash' | 'bank_transfer' | 'credit_card';

/** Bütün tutarlar kuruş cinsinden tam sayıdır: 125050 = 1.250,50 ₺ */
export type Cents = number;

export type Contact = {
    id: number;
    type: ContactType;
    name: string;
    tax_number: string | null;
    tax_office: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
};

export type ContactRow = Pick<
    Contact,
    'id' | 'type' | 'name' | 'tax_number' | 'phone' | 'email'
> & { balance: Cents };

export type InvoiceLine = {
    id: number;
    description: string;
    quantity: string;
    unit_price: Cents;
    discount_rate: number;
    vat_rate: number;
    net: Cents;
    vat: Cents;
    total: Cents;
};

export type Payment = {
    id: number;
    paid_at: string;
    amount: Cents;
    method: PaymentMethod;
    note: string | null;
    user: { id: number; name: string } | null;
};

export type Invoice = {
    id: number;
    type: InvoiceType;
    number: string;
    contact_id: number;
    issue_date: string;
    due_date: string;
    subtotal: Cents;
    discount_total: Cents;
    vat_total: Cents;
    total: Cents;
    paid_total: Cents;
    remaining: Cents;
    status: InvoiceStatus;
    notes: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    contact: Pick<Contact, 'id' | 'name'> & Partial<Contact>;
    lines?: InvoiceLine[];
    payments?: Payment[];
};

export type LedgerEntry = {
    date: string;
    kind: 'invoice' | 'payment';
    description: string;
    invoice_id: number;
    debit: Cents;
    credit: Cents;
    balance: Cents;
};

export type MonthlyTotal = { month: string; sales: Cents; purchase: Cents };
