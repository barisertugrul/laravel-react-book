import { Head, Link } from '@inertiajs/react';
import { FilePlus2, Pencil } from 'lucide-react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import type { Contact, LedgerEntry } from '@/types';

type Props = {
    contact: Contact & { type_label: string };
    entries: LedgerEntry[];
    balance: number;
};

export default function ContactShow({ contact, entries, balance }: Props) {
    const isCustomer = contact.type === 'customer';
    const balanceLabel = isCustomer ? 'Bize borcu' : 'Bizim borcumuz';

    return (
        <>
            <Head title={contact.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={contact.name}
                        description={[
                            contact.type_label,
                            contact.tax_number &&
                                `${contact.tax_office ?? ''} ${contact.tax_number}`.trim(),
                            contact.phone,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={ContactController.edit(contact.id)}>
                                <Pencil /> Düzenle
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={InvoiceController.create({
                                    query: {
                                        type: isCustomer ? 'sales' : 'purchase',
                                        contact: contact.id,
                                    },
                                })}
                            >
                                <FilePlus2 />{' '}
                                {isCustomer
                                    ? 'Satış faturası'
                                    : 'Alış faturası'}
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="text-sm text-muted-foreground">
                        {balanceLabel}
                    </div>
                    <div className="text-2xl font-semibold tabular-nums">
                        {formatMoney(balance)}
                    </div>
                </div>

                <div>
                    <h2 className="mb-2 font-medium">Hesap ekstresi</h2>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Tarih</th>
                                    <th className="p-3 font-medium">
                                        Açıklama
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Borç
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Alacak
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Bakiye
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {entries.map((e, i) => (
                                    <tr key={i}>
                                        <td className="p-3 whitespace-nowrap">
                                            {formatDate(e.date)}
                                        </td>
                                        <td className="p-3">
                                            <Link
                                                href={InvoiceController.show(
                                                    e.invoice_id,
                                                )}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {e.description}
                                            </Link>
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {e.debit
                                                ? formatMoney(e.debit)
                                                : ''}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {e.credit
                                                ? formatMoney(e.credit)
                                                : ''}
                                        </td>
                                        <td className="p-3 text-right font-medium tabular-nums">
                                            {formatMoney(e.balance)}
                                        </td>
                                    </tr>
                                ))}
                                {entries.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            Henüz hareket yok.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

ContactShow.layout = {
    breadcrumbs: [{ title: 'Cariler', href: ContactController.index() }],
};
