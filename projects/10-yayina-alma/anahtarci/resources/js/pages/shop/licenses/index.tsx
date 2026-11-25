import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import LicenseController from '@/actions/App/Http/Controllers/LicenseController';
import LicenseStatusBadge from '@/components/license-status-badge';
import { formatDate } from '@/lib/format';
import { home } from '@/routes';
import type { License } from '@/types';

export default function MyLicenses({ licenses }: { licenses: License[] }) {
    return (
        <>
            <Head title="Lisanslarım" />
            <h1 className="mb-6 text-2xl font-semibold">Lisanslarım</h1>
            <ul className="divide-y rounded-xl border bg-white dark:bg-neutral-900">
                {licenses.map((license) => (
                    <li key={license.id}>
                        <Link href={LicenseController.show(license.id)} className="flex flex-wrap items-center justify-between gap-4 p-4 hover:bg-muted/40">
                            <div>
                                <div className="font-semibold">{license.plan.product.name}</div>
                                <div className="text-sm text-muted-foreground">
                                    {license.plan.name} · {license.activations_count} / {license.max_sites ?? '∞'} site · güncelleme: {formatDate(license.updates_until)}
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <LicenseStatusBadge status={license.status} />
                                <ChevronRight className="size-4 text-muted-foreground" />
                            </div>
                        </Link>
                    </li>
                ))}
                {licenses.length === 0 && (
                    <li className="p-8 text-center text-muted-foreground">
                        Henüz lisansınız yok. <Link href={home()} className="underline">Eklentilere göz atın.</Link>
                    </li>
                )}
            </ul>
        </>
    );
}
