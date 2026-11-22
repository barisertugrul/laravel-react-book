import { Head, Link } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import Heading from '@/components/heading';
import MonthlyChart from '@/components/monthly-chart';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { dashboard } from '@/routes';
import type { Cents, MonthlyTotal } from '@/types';

type Props = {
    summary: {
        receivable: Cents;
        payable: Cents;
        overdue_count: number;
        overdue_amount: Cents;
    };
    monthly: MonthlyTotal[];
};

export default function Dashboard({ summary, monthly }: Props) {
    return (
        <>
            <Head title="Özet" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Özet"
                        description="Alacaklar, borçlar ve son altı ay"
                    />
                    <div className="flex gap-2">
                        <Button asChild>
                            <Link
                                href={InvoiceController.create({
                                    query: { type: 'sales' },
                                })}
                            >
                                <FilePlus2 /> Satış faturası
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={InvoiceController.create({
                                    query: { type: 'purchase' },
                                })}
                            >
                                Alış faturası
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        label="Tahsil edilecek"
                        value={formatMoney(summary.receivable)}
                        hint="Ödenmemiş satış faturaları"
                    />
                    <StatCard
                        label="Ödenecek"
                        value={formatMoney(summary.payable)}
                        hint="Ödenmemiş alış faturaları"
                    />
                    <StatCard
                        label="Vadesi geçen alacak"
                        value={formatMoney(summary.overdue_amount)}
                        hint={
                            summary.overdue_count > 0 ? (
                                <Link
                                    href={InvoiceController.index({
                                        query: {
                                            type: 'sales',
                                            status: 'overdue',
                                        },
                                    })}
                                    className="underline"
                                >
                                    {summary.overdue_count} fatura
                                </Link>
                            ) : (
                                'Gecikmiş fatura yok'
                            )
                        }
                        tone={summary.overdue_count > 0 ? 'danger' : undefined}
                    />
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="mb-4 font-medium">
                        Aylık satış ve alış (KDV dahil)
                    </h2>
                    <MonthlyChart data={monthly} />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Özet', href: dashboard() }],
};
