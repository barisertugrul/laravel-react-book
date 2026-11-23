import { Form, Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import LicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
import Heading from '@/components/heading';
import LicenseStatusBadge from '@/components/license-status-badge';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { License, Paginated } from '@/types';

type Props = { licenses: Paginated<License>; filters: { q: string; status: string | null } };

const tabs = [
    { value: null, label: 'Tümü' },
    { value: 'active', label: 'Etkin' },
    { value: 'revoked', label: 'İptal edilen' },
];

export default function AdminLicenses({ licenses, filters }: Props) {
    return (
        <>
            <Head title="Lisanslar" />
            <div className="space-y-4 p-4">
                <Heading title="Lisanslar" description="Satılan lisanslar, siteleri ve geçmişleri" />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-1">
                        {tabs.map((t) => (
                            <Link
                                key={t.label}
                                href={LicenseController.index({ query: { q: filters.q || undefined, status: t.value ?? undefined } })}
                                className={cn('rounded-full border px-3 py-1 text-sm', (filters.status ?? null) === t.value ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-accent')}
                            >
                                {t.label}
                            </Link>
                        ))}
                    </div>
                    {/* GET formu: arama adres çubuğuna yazılır, sayfa yenilense de kaybolmaz */}
                    <Form {...LicenseController.index.form()} className="flex gap-2">
                        {filters.status && <input type="hidden" name="status" value={filters.status} />}
                        <Input name="q" defaultValue={filters.q} placeholder="E-posta, ad veya site" className="w-64" />
                        <Button variant="outline" aria-label="Ara">
                            <Search />
                        </Button>
                    </Form>
                </div>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">#</th>
                                <th className="p-3 font-medium">Ürün / plan</th>
                                <th className="p-3 font-medium">Müşteri</th>
                                <th className="p-3 text-right font-medium">Site</th>
                                <th className="p-3 font-medium">Güncelleme</th>
                                <th className="p-3 font-medium">Durum</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {licenses.data.map((l) => (
                                <tr key={l.id} className="hover:bg-muted/30">
                                    <td className="p-3 font-mono">
                                        <Link href={LicenseController.show(l.id)} className="font-semibold underline-offset-4 hover:underline">
                                            #{l.id}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        {l.plan.product.name} <span className="text-muted-foreground">· {l.plan.name}</span>
                                    </td>
                                    <td className="p-3">{l.user?.email}</td>
                                    <td className="p-3 text-right tabular-nums">{l.activations_count} / {l.max_sites ?? '∞'}</td>
                                    <td className="p-3 whitespace-nowrap">{formatDate(l.updates_until)}</td>
                                    <td className="p-3">
                                        <LicenseStatusBadge status={l.status} />
                                    </td>
                                </tr>
                            ))}
                            {licenses.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-8 text-center text-muted-foreground">Lisans bulunamadı.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination page={licenses} />
            </div>
        </>
    );
}

AdminLicenses.layout = { breadcrumbs: [{ title: 'Lisanslar', href: LicenseController.index() }] };
