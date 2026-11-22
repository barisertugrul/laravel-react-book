import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import FilterTabs from '@/components/filter-tabs';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/hooks/use-filters';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { ContactRow, ContactType, Paginated } from '@/types';

type Filters = { type?: ContactType; q?: string };

export default function ContactsIndex({
    contacts,
    filters,
}: {
    contacts: Paginated<ContactRow>;
    filters: Filters;
}) {
    const { search, setSearch, apply } = useFilters(
        ContactController.index.url(),
        filters,
    );

    return (
        <>
            <Head title="Cariler" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Cariler"
                        description="Müşteriler, tedarikçiler ve güncel bakiyeleri"
                    />
                    <Button asChild>
                        <Link href={ContactController.create()}>
                            <Plus /> Yeni cari
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <FilterTabs
                        value={filters.type}
                        onChange={(type) => apply({ type })}
                        options={[
                            { label: 'Tümü' },
                            { value: 'customer', label: 'Müşteriler' },
                            { value: 'supplier', label: 'Tedarikçiler' },
                        ]}
                    />
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Ad veya vergi no"
                            className="pl-8"
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Ad / unvan</th>
                                <th className="p-3 font-medium">Tür</th>
                                <th className="p-3 font-medium">Vergi no</th>
                                <th className="p-3 font-medium">İletişim</th>
                                <th className="p-3 text-right font-medium">
                                    Bakiye
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {contacts.data.map((c) => (
                                <tr key={c.id} className="hover:bg-muted/30">
                                    <td className="p-3">
                                        <Link
                                            href={ContactController.show(c.id)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {c.name}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        {c.type === 'customer'
                                            ? 'Müşteri'
                                            : 'Tedarikçi'}
                                    </td>
                                    <td className="p-3 font-mono">
                                        {c.tax_number ?? '—'}
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {c.phone ?? c.email ?? '—'}
                                    </td>
                                    <td
                                        className={cn(
                                            'p-3 text-right tabular-nums',
                                            c.balance > 0 && 'font-medium',
                                        )}
                                    >
                                        {formatMoney(c.balance)}
                                    </td>
                                </tr>
                            ))}
                            {contacts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        Cari bulunamadı.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination page={contacts} />
            </div>
        </>
    );
}

ContactsIndex.layout = {
    breadcrumbs: [{ title: 'Cariler', href: ContactController.index() }],
};
