import { Head, Link } from '@inertiajs/react';
import { Download, Plus, Search } from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import FilterTabs from '@/components/filter-tabs';
import Heading from '@/components/heading';
import InvoiceStatusBadge from '@/components/invoice-status-badge';
import Pagination from '@/components/pagination';
import { selectClass } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/hooks/use-filters';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { invoices as exportInvoices } from '@/routes/export';
import type { Invoice, InvoiceType, Paginated } from '@/types';

type Filters = { type?: InvoiceType; status?: string; q?: string };

export default function InvoicesIndex({
    invoices,
    filters,
}: {
    invoices: Paginated<Invoice>;
    filters: Filters;
}) {
    const { search, setSearch, apply } = useFilters(
        InvoiceController.index.url(),
        filters,
    );
    // CSV, ekrandaki filtrelerle aynı faturaları indirir
    const exportUrl = exportInvoices.url({
        query: Object.fromEntries(Object.entries(filters).filter(([, v]) => v)),
    });

    return (
        <>
            <Head title="Faturalar" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Faturalar"
                        description="Satış ve alış faturaları"
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            {/* Dosya indirmesi bir Inertia ziyareti değildir: düz <a> kullanıyoruz */}
                            <a href={exportUrl}>
                                <Download /> CSV
                            </a>
                        </Button>
                        <Button asChild>
                            <Link
                                href={InvoiceController.create({
                                    query: { type: filters.type ?? 'sales' },
                                })}
                            >
                                <Plus /> Yeni fatura
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <FilterTabs
                        value={filters.type}
                        onChange={(type) => apply({ type })}
                        options={[
                            { label: 'Tümü' },
                            { value: 'sales', label: 'Satış' },
                            { value: 'purchase', label: 'Alış' },
                        ]}
                    />
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            apply({ status: e.target.value || undefined })
                        }
                        className={selectClass}
                    >
                        <option value="">Her durum</option>
                        <option value="outstanding">Ödenmemiş</option>
                        <option value="overdue">Vadesi geçmiş</option>
                        <option value="paid">Ödenmiş</option>
                        <option value="cancelled">İptal edilmiş</option>
                    </select>
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Fatura no veya cari"
                            className="pl-8"
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Numara</th>
                                <th className="p-3 font-medium">Cari</th>
                                <th className="p-3 font-medium">Tarih</th>
                                <th className="p-3 font-medium">Vade</th>
                                <th className="p-3 text-right font-medium">
                                    Toplam
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Kalan
                                </th>
                                <th className="p-3 font-medium">Durum</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {invoices.data.map((inv) => (
                                <tr key={inv.id} className="hover:bg-muted/30">
                                    <td className="p-3 font-mono">
                                        <Link
                                            href={InvoiceController.show(
                                                inv.id,
                                            )}
                                            className="font-semibold underline-offset-4 hover:underline"
                                        >
                                            {inv.number}
                                        </Link>
                                    </td>
                                    <td className="p-3">{inv.contact.name}</td>
                                    <td className="p-3 whitespace-nowrap">
                                        {formatDate(inv.issue_date)}
                                    </td>
                                    <td className="p-3 whitespace-nowrap">
                                        {formatDate(inv.due_date)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatMoney(inv.total)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {inv.remaining
                                            ? formatMoney(inv.remaining)
                                            : '—'}
                                    </td>
                                    <td className="p-3">
                                        <InvoiceStatusBadge
                                            status={inv.status}
                                        />
                                    </td>
                                </tr>
                            ))}
                            {invoices.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        Fatura bulunamadı.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination page={invoices} />
            </div>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Faturalar', href: InvoiceController.index() }],
};
