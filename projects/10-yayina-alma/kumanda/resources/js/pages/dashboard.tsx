import { Head, Link } from '@inertiajs/react';
import { Plus, RefreshCw } from 'lucide-react';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import HealthBadge, { healthStyles } from '@/components/health-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { daysUntil, hostOf, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { HealthStatus, SiteSummary } from '@/types';

type Props = { sites: SiteSummary[]; counts: Record<HealthStatus, number> };

const order: HealthStatus[] = ['critical', 'warning', 'ok', 'unknown'];

export default function Dashboard({ sites, counts }: Props) {
    return (
        <>
            <Head title="Siteler" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading title="Siteler" description="Son kontrol sonuçları; sorunlu siteler en üstte." />
                    <Button asChild>
                        <Link href={SiteController.create()}>
                            <Plus /> Site ekle
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {order.map((status) => (
                        <div key={status} className="rounded-xl border p-4">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <span className={cn('size-2 rounded-full', healthStyles[status].dot)} />
                                {healthStyles[status].label}
                            </div>
                            <div className="mt-1 text-3xl font-semibold tabular-nums">{counts[status] ?? 0}</div>
                        </div>
                    ))}
                </div>

                {/* Telefonda yalnızca site, durum ve son kontrol; ayrıntılar site sayfasında */}
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Site</th>
                                <th className="p-3 font-medium">Durum</th>
                                <th className="hidden p-3 font-medium md:table-cell">WordPress</th>
                                <th className="hidden p-3 font-medium md:table-cell">PHP</th>
                                <th className="hidden p-3 text-right font-medium md:table-cell">Güncelleme</th>
                                <th className="hidden p-3 text-right font-medium md:table-cell">Yanıt</th>
                                <th className="hidden p-3 font-medium md:table-cell">SSL</th>
                                <th className="p-3 font-medium">Son kontrol</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {sites.map((site) => (
                                <tr key={site.id} className="hover:bg-muted/30">
                                    <td className="p-3">
                                        <Link href={SiteController.show(site.id)} className="font-medium hover:underline">
                                            {site.name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">{hostOf(site.url)}</div>
                                    </td>
                                    <td className="p-3">
                                        <HealthBadge status={site.health_status} />
                                    </td>
                                    <td className="hidden p-3 tabular-nums md:table-cell">{site.wp_version ?? '—'}</td>
                                    <td className="hidden p-3 tabular-nums md:table-cell">{site.php_version ?? '—'}</td>
                                    <td className={cn('hidden p-3 text-right tabular-nums md:table-cell', site.pending_updates > 0 && 'font-medium text-amber-700 dark:text-amber-400')}>
                                        {site.pending_updates}
                                    </td>
                                    <td className="hidden p-3 text-right tabular-nums md:table-cell">{site.response_ms === null ? '—' : `${site.response_ms} ms`}</td>
                                    <td className="hidden p-3 whitespace-nowrap md:table-cell">
                                        <SslCell url={site.url} expiresAt={site.ssl_expires_at} />
                                    </td>
                                    <td className="p-3 whitespace-nowrap text-muted-foreground">{timeAgo(site.last_checked_at)}</td>
                                </tr>
                            ))}
                            {sites.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="p-10 text-center text-muted-foreground">
                                        <RefreshCw className="mx-auto mb-2 size-5" />
                                        Henüz site yok. WordPress sitesine Ajans Asistanı eklentisini kurun, sonra “Site ekle”.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function SslCell({ url, expiresAt }: { url: string; expiresAt: string | null }) {
    if (!url.startsWith('https://')) {
        return <span className="text-amber-700 dark:text-amber-400">HTTPS yok</span>;
    }

    if (!expiresAt) {
        return <span className="text-muted-foreground">—</span>;
    }

    const days = daysUntil(expiresAt);

    return <span className={cn('tabular-nums', days <= 14 && 'font-medium text-amber-700 dark:text-amber-400', days <= 3 && 'text-red-700 dark:text-red-400')}>{days} gün</span>;
}

Dashboard.layout = { breadcrumbs: [{ title: 'Siteler', href: dashboard() }] };
