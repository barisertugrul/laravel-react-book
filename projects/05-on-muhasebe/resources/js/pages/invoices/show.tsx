import { Form, Head, Link } from '@inertiajs/react';
import { Ban, FileDown } from 'lucide-react';
import { useState } from 'react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import FormField, { selectClass } from '@/components/form-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import InvoiceStatusBadge from '@/components/invoice-status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { cancel, pdf } from '@/routes/invoices';
import { store as storePayment } from '@/routes/invoices/payments';
import type { Invoice, Option } from '@/types';

type Props = {
    invoice: Required<Invoice>;
    vatBreakdown: Record<string, number>;
    paymentMethods: Option[];
};

const today = () => new Date().toLocaleDateString('sv-SE'); // "2026-11-23" biçimi, yerel saatle

/** Kuruşu forma yazılacak metne çevirir: 125050 → "1250,50" */
const toInput = (cents: number) =>
    `${Math.trunc(cents / 100)},${String(cents % 100).padStart(2, '0')}`;

export default function InvoiceShow({
    invoice,
    vatBreakdown,
    paymentMethods,
}: Props) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const canPay = !invoice.cancelled_at && invoice.remaining > 0;
    const canCancel = !invoice.cancelled_at && invoice.paid_total === 0;

    return (
        <>
            <Head title={invoice.number} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Heading
                            title={invoice.number}
                            description={`${invoice.type === 'sales' ? 'Satış' : 'Alış'} faturası · ${formatDate(invoice.issue_date)} · vade ${formatDate(invoice.due_date)}`}
                        />
                        <InvoiceStatusBadge status={invoice.status} />
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <a href={pdf.url(invoice.id)}>
                                <FileDown /> PDF
                            </a>
                        </Button>
                        {canCancel && (
                            <Button
                                variant="outline"
                                onClick={() => setCancelOpen(true)}
                            >
                                <Ban /> İptal et
                            </Button>
                        )}
                    </div>
                </div>

                {invoice.cancelled_at && (
                    <div className="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                        Bu fatura {formatDate(invoice.cancelled_at)} tarihinde
                        iptal edildi. Gerekçe: {invoice.cancel_reason}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-xl border p-4 text-sm">
                            <Link
                                href={ContactController.show(
                                    invoice.contact.id,
                                )}
                                className="font-medium underline-offset-4 hover:underline"
                            >
                                {invoice.contact.name}
                            </Link>
                            <div className="text-muted-foreground">
                                {[
                                    invoice.contact.tax_office,
                                    invoice.contact.tax_number,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Açıklama
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Miktar
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Birim fiyat
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            İsk.
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            KDV
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Tutar
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invoice.lines.map((l) => (
                                        <tr key={l.id}>
                                            <td className="p-3">
                                                {l.description}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {Number(
                                                    l.quantity,
                                                ).toLocaleString('tr-TR')}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {formatMoney(l.unit_price)}
                                            </td>
                                            <td className="p-3 text-right">
                                                {l.discount_rate
                                                    ? `%${l.discount_rate}`
                                                    : ''}
                                            </td>
                                            <td className="p-3 text-right">
                                                %{l.vat_rate}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {formatMoney(l.net)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <dl className="ml-auto w-72 space-y-1 text-sm">
                            <Row
                                label="Ara toplam"
                                value={formatMoney(invoice.subtotal)}
                            />
                            {invoice.discount_total > 0 && (
                                <Row
                                    label="İskonto"
                                    value={`-${formatMoney(invoice.discount_total)}`}
                                />
                            )}
                            {Object.entries(vatBreakdown).map(([rate, vat]) => (
                                <Row
                                    key={rate}
                                    label={`KDV (%${rate})`}
                                    value={formatMoney(vat)}
                                />
                            ))}
                            <div className="flex justify-between border-t pt-2 text-base font-semibold">
                                <dt>Genel toplam</dt>
                                <dd className="tabular-nums">
                                    {formatMoney(invoice.total)}
                                </dd>
                            </div>
                            <Row
                                label="Ödenen"
                                value={formatMoney(invoice.paid_total)}
                            />
                            <Row
                                label="Kalan"
                                value={formatMoney(invoice.remaining)}
                            />
                        </dl>
                    </div>

                    <div className="space-y-4">
                        <h2 className="font-medium">
                            {invoice.type === 'sales'
                                ? 'Tahsilatlar'
                                : 'Ödemeler'}
                        </h2>
                        {invoice.payments.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Henüz ödeme yok.
                            </p>
                        )}
                        <ul className="divide-y rounded-xl border text-sm">
                            {invoice.payments.map((p) => (
                                <li
                                    key={p.id}
                                    className="flex justify-between gap-2 p-3"
                                >
                                    <div>
                                        <div>
                                            {formatDate(p.paid_at)} ·{' '}
                                            {
                                                paymentMethods.find(
                                                    (m) => m.value === p.method,
                                                )?.label
                                            }
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {[p.note, p.user?.name]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    </div>
                                    <div className="font-medium tabular-nums">
                                        {formatMoney(p.amount)}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {canPay && (
                            <Form
                                {...storePayment.form(invoice.id)}
                                resetOnSuccess
                                className="space-y-3 rounded-xl border p-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <h3 className="text-sm font-medium">
                                            Ödeme gir
                                        </h3>
                                        {/* Varsayılan tutar: kalan bakiyenin tamamı */}
                                        <FormField
                                            name="amount"
                                            label="Tutar (₺)"
                                            inputMode="decimal"
                                            defaultValue={toInput(
                                                invoice.remaining,
                                            )}
                                            error={errors.amount}
                                        />
                                        <FormField
                                            name="paid_at"
                                            label="Tarih"
                                            type="date"
                                            defaultValue={today()}
                                            max={today()}
                                            error={errors.paid_at}
                                        />
                                        <div className="grid gap-2">
                                            <Label htmlFor="method">
                                                Yöntem
                                            </Label>
                                            <select
                                                id="method"
                                                name="method"
                                                className={selectClass}
                                            >
                                                {paymentMethods.map((m) => (
                                                    <option
                                                        key={m.value}
                                                        value={m.value}
                                                    >
                                                        {m.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.method}
                                            />
                                        </div>
                                        <FormField
                                            name="note"
                                            label="Not"
                                            placeholder="Dekont no, açıklama…"
                                            error={errors.note}
                                        />
                                        <Button
                                            className="w-full"
                                            disabled={processing}
                                        >
                                            Kaydet
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <Form
                        {...cancel.form(invoice.id)}
                        onSuccess={() => setCancelOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {invoice.number} iptal edilsin mi?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Fatura silinmez; numarasıyla birlikte
                                        kayıtlarda “İptal” olarak kalır,
                                        bakiyelere ve raporlara girmez. Bu işlem
                                        geri alınamaz.
                                    </DialogDescription>
                                </DialogHeader>
                                <FormField
                                    name="reason"
                                    label="İptal gerekçesi"
                                    autoFocus
                                    error={errors.reason}
                                />
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setCancelOpen(false)}
                                    >
                                        Vazgeç
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Faturayı iptal et
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="tabular-nums">{value}</dd>
        </div>
    );
}

InvoiceShow.layout = {
    breadcrumbs: [{ title: 'Faturalar', href: InvoiceController.index() }],
};
