import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option, Paginated, Ticket, TicketStatus } from '@/types';

type Filters = { status?: TicketStatus; q?: string; mine?: string };

type Props = {
    tickets: Paginated<Ticket>;
    filters: Filters;
    counts: Partial<Record<TicketStatus, number>>;
    statuses: Option<TicketStatus>[];
};

export default function TicketsIndex({ tickets, filters, counts, statuses }: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const isFirstRender = useRef(true);
    const total = Object.values(counts).reduce((sum, n) => sum + (n ?? 0), 0);

    /** Filtreyi adres çubuğuna yazar ve listeyi sunucudan yeniden ister. */
    function applyFilters(next: Filters) {
        const query = { ...filters, ...next };
        // Boş filtreleri adresten çıkar (?status=&q= yerine temiz bir adres)
        const clean = Object.fromEntries(Object.entries(query).filter(([, v]) => v));

        router.get(TicketController.index.url(), clean, {
            preserveState: true, // arama kutusundaki yazı ve odak kaybolmasın
            preserveScroll: true,
            replace: true, // her harf tarayıcı geçmişine yeni bir kayıt eklemesin
        });
    }

    // Arama: kullanıcı yazmayı 300 ms bıraktığında istek gönder (debounce)
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timer = setTimeout(() => applyFilters({ q: search }), 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const { auth } = usePage().props;

    return (
        <>
            <Head title="Servis kayıtları" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Servis kayıtları"
                        description={auth.user.role === 'admin' ? 'Tüm kayıtlar' : 'Size atanan ve henüz atanmamış kayıtlar'}
                    />
                    <Button asChild>
                        <Link href={TicketController.create()}>
                            <Plus /> Yeni kayıt
                        </Link>
                    </Button>
                </div>

                {/* Durum sekmeleri: her durumda kaç kayıt var */}
                <div className="flex flex-wrap gap-1">
                    <FilterTab active={!filters.status} onClick={() => applyFilters({ status: undefined })} label="Tümü" count={total} />
                    {statuses.map((s) => (
                        <FilterTab key={s.value} active={filters.status === s.value} onClick={() => applyFilters({ status: s.value })} label={s.label} count={counts[s.value] ?? 0} />
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <div className="relative w-full max-w-sm">
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Servis no, müşteri, telefon, marka..."
                            className="pl-8"
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={!!filters.mine} onCheckedChange={(c) => applyFilters({ mine: c ? '1' : undefined })} />
                        Yalnızca bana atananlar
                    </label>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Servis no</th>
                                <th className="p-3 font-medium">Müşteri</th>
                                <th className="p-3 font-medium">Cihaz</th>
                                <th className="p-3 font-medium">Durum</th>
                                <th className="p-3 font-medium">Teknisyen</th>
                                <th className="p-3 font-medium">Geliş</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {tickets.data.map((ticket) => (
                                <tr key={ticket.id} className="hover:bg-muted/30">
                                    <td className="p-3 font-mono">
                                        <Link href={TicketController.show(ticket.code)} className="font-semibold underline-offset-4 hover:underline">
                                            {ticket.code}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        {ticket.customer?.name}
                                        <span className="block text-xs text-muted-foreground">{ticket.customer?.phone}</span>
                                    </td>
                                    <td className="p-3">
                                        {ticket.brand} {ticket.model}
                                        <span className="block text-xs text-muted-foreground">{ticket.device_type}</span>
                                    </td>
                                    <td className="p-3"><StatusBadge status={ticket.status} /></td>
                                    <td className="p-3">{ticket.technician?.name ?? <span className="text-muted-foreground">Atanmadı</span>}</td>
                                    <td className="p-3 whitespace-nowrap">{formatDate(ticket.created_at)}</td>
                                </tr>
                            ))}
                            {tickets.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-8 text-center text-muted-foreground">Kayıt bulunamadı.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination page={tickets} />
            </div>
        </>
    );
}

function FilterTab({ active, onClick, label, count }: { active: boolean; onClick: () => void; label: string; count: number }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn('rounded-full border px-3 py-1 text-sm transition', active ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-accent')}
        >
            {label} <span className="ml-1 opacity-70">{count}</span>
        </button>
    );
}

TicketsIndex.layout = {
    breadcrumbs: [{ title: 'Servis kayıtları', href: TicketController.index() }],
};
