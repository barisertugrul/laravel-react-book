import { Head, Link } from '@inertiajs/react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import Heading from '@/components/heading';
import MonthlyChart from '@/components/monthly-chart';
import StatCard from '@/components/stat-card';
import { formatDate, formatMonth } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { reports } from '@/routes';
import type { Cents, MonthlyTotal } from '@/types';

type OverdueRow = {
    id: number;
    number: string;
    contact: string;
    due_date: string;
    days: number;
    remaining: Cents;
};
type Props = {
    monthly: MonthlyTotal[];
    overdue: OverdueRow[];
    aging: Record<string, Cents>;
};

export default function Reports({ monthly, overdue, aging }: Props) {
    const yearSales = monthly.reduce((s, m) => s + m.sales, 0);
    const yearPurchase = monthly.reduce((s, m) => s + m.purchase, 0);

    return (
        <>
            <Head title="Raporlar" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Raporlar"
                    description="Son 12 ay ve vadesi geçmiş alacaklar"
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        label="12 aylık satış"
                        value={formatMoney(yearSales)}
                    />
                    <StatCard
                        label="12 aylık alış"
                        value={formatMoney(yearPurchase)}
                    />
                    <StatCard
                        label="Fark"
                        value={formatMoney(yearSales - yearPurchase)}
                        hint="KDV dahil tutarlarla, kaba bir gösterge"
                    />
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="mb-4 font-medium">Aylık satış ve alış</h2>
                    <MonthlyChart data={monthly} />
                    <table className="mt-6 w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-1 font-medium">Ay</th>
                                <th className="py-1 text-right font-medium">
                                    Satış
                                </th>
                                <th className="py-1 text-right font-medium">
                                    Alış
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {monthly.map((m) => (
                                <tr key={m.month}>
                                    <td className="py-1">
                                        {formatMonth(m.month)}
                                    </td>
                                    <td className="py-1 text-right tabular-nums">
                                        {formatMoney(m.sales)}
                                    </td>
                                    <td className="py-1 text-right tabular-nums">
                                        {formatMoney(m.purchase)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="space-y-3">
                    <h2 className="font-medium">
                        Vadesi geçmiş alacaklar (yaşlandırma)
                    </h2>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {Object.entries(aging).map(([bucket, amount]) => (
                            <StatCard
                                key={bucket}
                                label={`${bucket} gün`}
                                value={formatMoney(amount)}
                                tone={
                                    bucket === '90+' && amount > 0
                                        ? 'danger'
                                        : undefined
                                }
                            />
                        ))}
                    </div>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Fatura</th>
                                    <th className="p-3 font-medium">Müşteri</th>
                                    <th className="p-3 font-medium">Vade</th>
                                    <th className="p-3 text-right font-medium">
                                        Gecikme
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Kalan
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {overdue.map((o) => (
                                    <tr key={o.id}>
                                        <td className="p-3 font-mono">
                                            <Link
                                                href={InvoiceController.show(
                                                    o.id,
                                                )}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {o.number}
                                            </Link>
                                        </td>
                                        <td className="p-3">{o.contact}</td>
                                        <td className="p-3">
                                            {formatDate(o.due_date)}
                                        </td>
                                        <td className="p-3 text-right">
                                            {o.days} gün
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {formatMoney(o.remaining)}
                                        </td>
                                    </tr>
                                ))}
                                {overdue.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            Vadesi geçmiş alacak yok.
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

Reports.layout = {
    breadcrumbs: [{ title: 'Raporlar', href: reports() }],
};
