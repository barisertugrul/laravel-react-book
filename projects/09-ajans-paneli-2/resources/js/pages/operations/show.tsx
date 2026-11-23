import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import { Ban, Radio, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import OperationController from '@/actions/App/Http/Controllers/OperationController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import Heading from '@/components/heading';
import TargetStatusBadge from '@/components/target-status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatTime, hostOf } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { OperationDetail, OperationProgressedEvent, OperationSummary, OperationTarget } from '@/types';

type Props = {
    operation: OperationDetail;
    summary: OperationSummary;
    targets: OperationTarget[];
    realtime: boolean;
    agencyId: number;
};

export default function OperationShow({ operation, summary: initialSummary, targets: initialTargets, realtime, agencyId }: Props) {
    const [targets, setTargets] = useState(initialTargets);
    const [summary, setSummary] = useState(initialSummary);
    const [finishedAt, setFinishedAt] = useState(operation.finished_at);

    // Sayfa sunucudan yeniden yüklenince (yoklama, iptal) yerel durum yeni verilerle başlar
    useEffect(() => {
        setTargets(initialTargets);
        setSummary(initialSummary);
        setFinishedAt(operation.finished_at);
    }, [initialTargets, initialSummary, operation.finished_at]);

    /** Kanaldan gelen olay: yalnızca değişen satır güncellenir, sayfa yeniden yüklenmez */
    // useCallback: dinleyici her çizimde yeniden kurulmasın (useEcho bağımlılık listesine bakar)
    const apply = useCallback(
        (event: OperationProgressedEvent) => {
            if (event.operation_id !== operation.id) {
                return; // aynı ajansın başka bir işlemi
            }

            setSummary(event.summary);
            setFinishedAt(event.finished_at);

            if (event.target) {
                const changed = event.target;
                setTargets((rows) => rows.map((row) => (row.id === changed.id ? changed : row)));
            }
        },
        [operation.id],
    );

    const done = summary.succeeded + summary.failed + summary.cancelled;
    const percent = summary.total === 0 ? 0 : Math.round((done / summary.total) * 100);
    const running = finishedAt === null;

    return (
        <>
            <Head title={operation.label} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={operation.label}
                        description={`${operation.user ?? 'Bilinmeyen kullanıcı'} · ${formatDateTime(operation.created_at)}${finishedAt ? ` · bitiş ${formatTime(finishedAt)}` : ''}`}
                    />
                    <div className="flex items-center gap-2">
                        {running && (realtime ? <LiveUpdates agencyId={agencyId} onEvent={apply} /> : <PollUpdates />)}
                        {running && summary.pending > 0 && (
                            <Form {...OperationController.cancel.form(operation.id)} options={{ preserveScroll: true }}>
                                {({ processing }) => (
                                    <Button variant="outline" disabled={processing} data-test="cancel-operation-button">
                                        <Ban /> Bekleyenleri iptal et
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>

                <div className="space-y-2 rounded-xl border p-4">
                    <div className="flex flex-wrap justify-between gap-2 text-sm">
                        <span className="font-medium tabular-nums">
                            {done}/{summary.total} site
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            {summary.succeeded} tamam · {summary.failed} hata · {summary.running} çalışıyor · {summary.pending} sırada
                            {summary.cancelled > 0 && ` · ${summary.cancelled} iptal`}
                        </span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100}>
                        <div
                            className={cn('h-full rounded-full transition-[width] duration-500', summary.failed > 0 ? 'bg-amber-500' : 'bg-emerald-500')}
                            style={{ width: `${percent}%` }}
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Site</th>
                                <th className="p-3 font-medium">Durum</th>
                                <th className="p-3 font-medium">Sonuç</th>
                                <th className="hidden p-3 text-right font-medium md:table-cell">Süre</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {targets.map((target) => (
                                <tr key={target.id} className={cn(target.status === 'running' && 'bg-sky-50/60 dark:bg-sky-950/20')}>
                                    <td className="p-3">
                                        <Link href={SiteController.show(target.site_id)} className="font-medium hover:underline">
                                            {target.site_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">{hostOf(target.site_url)}</div>
                                    </td>
                                    <td className="p-3">
                                        <TargetStatusBadge status={target.status} />
                                    </td>
                                    <td className={cn('p-3', target.status === 'failed' && 'text-red-700 dark:text-red-400')}>{target.message ?? '—'}</td>
                                    <td className="hidden p-3 text-right text-muted-foreground tabular-nums md:table-cell">{duration(target)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

/**
 * Ajansın kanalını dinler. Bağlantı yoksa (Reverb kapalı, ağ koptu) yoklamaya geçer:
 * canlı yayın bir kolaylıktır, ilerlemeyi görmenin TEK yolu olmamalı.
 */
function LiveUpdates({ agencyId, onEvent }: { agencyId: number; onEvent: (event: OperationProgressedEvent) => void }) {
    // Olay adı başındaki nokta: broadcastAs() ile verdiğimiz adı olduğu gibi dinle (App\Events\ öneki ekleme)
    useEcho<OperationProgressedEvent>(`agencies.${agencyId}`, '.operation.progressed', onEvent, [onEvent]);
    const status = useConnectionStatus();

    if (status !== 'connected') {
        return <PollUpdates />;
    }

    return (
        <span className="inline-flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400" data-test="live-indicator">
            <Radio className="size-4 animate-pulse" /> Canlı
        </span>
    );
}

/** Yayın yoksa: 3 saniyede bir yalnızca değişen verileri iste */
function PollUpdates() {
    usePoll(3_000, { only: ['summary', 'targets', 'operation'] });

    return (
        <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground" data-test="poll-indicator">
            <RefreshCw className="size-4 animate-spin [animation-duration:3s]" /> Yenileniyor
        </span>
    );
}

function duration(target: OperationTarget): string {
    if (!target.started_at || !target.finished_at) {
        return '—';
    }

    // Zaman damgaları saniye hassasiyetinde: ondalık göstermek yanıltıcı olurdu
    const seconds = Math.round((new Date(target.finished_at).getTime() - new Date(target.started_at).getTime()) / 1000);

    return seconds < 1 ? '<1 sn' : `${seconds} sn`;
}

OperationShow.layout = { breadcrumbs: [{ title: 'İşlemler', href: OperationController.index() }] };
