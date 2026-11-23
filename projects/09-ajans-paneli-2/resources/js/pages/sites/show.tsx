import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { AlertTriangle, CircleCheck, ExternalLink, KeyRound, OctagonAlert, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import SiteCheckController from '@/actions/App/Http/Controllers/SiteCheckController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import HealthBadge from '@/components/health-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ResponseChart from '@/components/response-chart';
import { Button } from '@/components/ui/button';
import { daysUntil, formatDate, formatDateTime, hostOf, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { AgentLicense, AgentReport, Issue, SiteCheck, SiteDetail } from '@/types';

type Props = { site: SiteDetail; checks: SiteCheck[]; report: AgentReport | null };

export default function SiteShow({ site, checks, report }: Props) {
    const { auth } = usePage().props;
    const [confirming, setConfirming] = useState(false);
    const latest = checks[0] ?? null;

    // Kontrol kuyrukta çalışır; sonuç gelince sayfa kendiliğinden yenilensin
    usePoll(10_000, { only: ['site', 'checks', 'report'] });

    return (
        <>
            <Head title={site.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <Heading title={site.name} description={hostOf(site.url)} />
                        <HealthBadge status={site.health_status} />
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={site.url} target="_blank" rel="noreferrer">
                                <ExternalLink /> Siteyi aç
                            </a>
                        </Button>
                        <Form {...SiteCheckController.form(site.id)} options={{ preserveScroll: true }}>
                            {({ processing }) => (
                                <Button disabled={processing} data-test="check-now-button">
                                    <RefreshCw className={cn(processing && 'animate-spin')} /> Şimdi kontrol et
                                </Button>
                            )}
                        </Form>
                    </div>
                </div>

                <dl className="grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-3 lg:grid-cols-6">
                    <Fact label="WordPress" value={site.wp_version} />
                    <Fact label="PHP" value={site.php_version} />
                    <Fact label="Ajans Asistanı" value={site.agent_version} />
                    <Fact label="Bekleyen güncelleme" value={String(site.pending_updates)} warn={site.pending_updates > 0} />
                    <Fact label="Yanıt süresi" value={site.response_ms === null ? null : `${site.response_ms} ms`} />
                    <Fact
                        label="SSL"
                        value={!site.url.startsWith('https://') ? 'HTTPS yok' : site.ssl_expires_at ? `${formatDate(site.ssl_expires_at)} (${daysUntil(site.ssl_expires_at)} gün)` : null}
                        warn={!site.url.startsWith('https://') || (site.ssl_expires_at !== null && daysUntil(site.ssl_expires_at) <= 14)}
                    />
                </dl>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="space-y-2 lg:col-span-2">
                        <h2 className="font-medium">Bulgular</h2>
                        <IssueList check={latest} />
                    </section>

                    <section className="space-y-2">
                        <h2 className="font-medium">Yanıt süresi (son {checks.length} kontrol)</h2>
                        <div className="rounded-xl border p-4">
                            <ResponseChart checks={checks} />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Son kontrol {timeAgo(site.last_checked_at)}
                            {site.status_changed_at && ` · durum ${formatDateTime(site.status_changed_at)} tarihinden beri aynı`}
                        </p>
                    </section>
                </div>

                {report?.license && <LicenseSummary license={report.license} />}

                {report && (
                    <section className="space-y-2">
                        <h2 className="font-medium">Eklentiler ({report.plugins.length})</h2>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Eklenti</th>
                                        <th className="p-3 font-medium">Durum</th>
                                        <th className="p-3 font-medium">Sürüm</th>
                                        <th className="p-3 font-medium">Güncelleme</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {report.plugins.map((plugin) => (
                                        <tr key={plugin.file}>
                                            <td className="p-3 font-medium">{plugin.name}</td>
                                            <td className="p-3 text-muted-foreground">{plugin.active ? 'Etkin' : 'Devre dışı'}</td>
                                            <td className="p-3 tabular-nums">{plugin.version}</td>
                                            <td className="p-3 tabular-nums">
                                                {plugin.update ? <span className="font-medium text-amber-700 dark:text-amber-400">{plugin.update}</span> : <span className="text-muted-foreground">Güncel</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                <RepairConnection site={site} keysBroken={latest?.issues.some((issue) => issue.code === 'agent_keys') ?? false} />

                <section className="space-y-2">
                    <h2 className="font-medium">Kontrol geçmişi</h2>
                    <ol className="divide-y rounded-xl border text-sm">
                        {checks.slice(0, 12).map((check) => (
                            <li key={check.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 p-3">
                                <span className="w-36 shrink-0 text-muted-foreground tabular-nums">{formatDateTime(check.checked_at)}</span>
                                <HealthBadge status={check.status} />
                                <span className="tabular-nums">{check.http_status ? `HTTP ${check.http_status}` : '—'}</span>
                                <span className="tabular-nums">{check.response_ms === null ? '' : `${check.response_ms} ms`}</span>
                                <span className="text-muted-foreground">{check.error ?? `${check.issues.length} bulgu`}</span>
                            </li>
                        ))}
                    </ol>
                </section>

                {auth.agency?.role === 'owner' && (
                    <section className="space-y-3 rounded-xl border border-red-200 p-4 dark:border-red-900">
                        <h2 className="font-medium text-red-700 dark:text-red-400">Siteyi panelden kaldır</h2>
                        <p className="text-sm text-muted-foreground">Kontrol geçmişi silinir. WordPress sitesine dokunulmaz; yeniden bağlamak için yeni bir bağlantı anahtarı gerekir.</p>
                        {confirming ? (
                            <Form {...SiteController.destroy.form(site.id)} className="flex gap-2">
                                {({ processing }) => (
                                    <>
                                        <Button variant="destructive" disabled={processing}>
                                            <Trash2 /> Evet, kaldır
                                        </Button>
                                        <Button type="button" variant="outline" onClick={() => setConfirming(false)}>
                                            Vazgeç
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <Button variant="outline" onClick={() => setConfirming(true)}>
                                <Trash2 /> Kaldır
                            </Button>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function Fact({ label, value, warn = false }: { label: string; value: string | null; warn?: boolean }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={cn('font-medium tabular-nums', warn && 'text-amber-700 dark:text-amber-400')}>{value ?? '—'}</dd>
        </div>
    );
}

function IssueList({ check }: { check: SiteCheck | null }) {
    if (!check) {
        return <p className="rounded-xl border p-6 text-center text-sm text-muted-foreground">İlk kontrol bekleniyor…</p>;
    }

    if (check.issues.length === 0) {
        return (
            <p className="flex items-center gap-2 rounded-xl border p-4 text-sm text-emerald-700 dark:text-emerald-400">
                <CircleCheck className="size-4" /> Sorun bulunmadı.
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-xl border text-sm">
            {check.issues.map((issue: Issue) => (
                <li key={issue.code} className="flex items-start gap-3 p-3">
                    {issue.level === 'critical' ? (
                        <OctagonAlert className="mt-0.5 size-4 shrink-0 text-red-600" />
                    ) : (
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                    )}
                    <span>{issue.message}</span>
                </li>
            ))}
        </ul>
    );
}

/**
 * Anahtar yenileme: site silinmeden yeni bir bağlantı anahtarıyla yeniden bağlanır.
 * Son kontrol anahtar sorunu bulduysa (agent_keys) bölüm açık ve uyarı renginde gelir.
 */
function RepairConnection({ site, keysBroken }: { site: SiteDetail; keysBroken: boolean }) {
    const [open, setOpen] = useState(keysBroken);

    if (!open) {
        return (
            <Button variant="outline" onClick={() => setOpen(true)} data-test="open-repair-button">
                <KeyRound /> Bağlantıyı yenile
            </Button>
        );
    }

    return (
        <section className={cn('space-y-3 rounded-xl border p-4', keysBroken && 'border-amber-300 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/20')}>
            <h2 className="flex items-center gap-2 font-medium">
                <KeyRound className="size-4" /> Bağlantıyı yenile
            </h2>
            <p className="text-sm text-muted-foreground">
                {keysBroken ? 'Panel bu siteyle artık imzalı konuşamıyor. ' : ''}
                Sitede <strong className="text-foreground">Ayarlar → Ajans Asistanı</strong> sayfasından yeni bir bağlantı anahtarı oluşturup buraya yapıştırın. Kontrol geçmişi ve işlemler korunur;
                panelin bu siteye özel anahtarı da yenilenir.
            </p>
            <Form {...SiteController.repair.form(site.id)} options={{ preserveScroll: true }} disableWhileProcessing className="space-y-3">
                {({ processing, errors }) => (
                    <>
                        <textarea
                            name="connection_key"
                            required
                            rows={3}
                            spellCheck={false}
                            placeholder="AA1.eyJ1Ijoi…"
                            aria-label="Bağlantı anahtarı"
                            className="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs break-all shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                        <InputError message={errors.connection_key} />
                        <Button disabled={processing} data-test="repair-site-button">
                            <KeyRound /> Yeniden bağla
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}

/** Ajans Asistanı'nın kendi lisansı (sitenin son raporundan) */
function LicenseSummary({ license }: { license: AgentLicense }) {
    if (license.state === 'none') {
        return <p className="rounded-xl border p-4 text-sm text-muted-foreground">Ajans Asistanı lisansı girilmemiş: eklenti çalışır ama yeni sürümlerini alamaz.</p>;
    }

    return (
        <dl className={cn('grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-4', license.state === 'invalid' && 'border-amber-300 dark:border-amber-800')}>
            <Fact label="Ajans Asistanı lisansı" value={license.state === 'active' ? `Etkin · ${license.plan ?? ''}` : 'Geçersiz'} warn={license.state === 'invalid'} />
            <Fact label="Site kullanımı" value={license.max_sites === null ? `${license.sites_used} (sınırsız)` : `${license.sites_used}/${license.max_sites}`} />
            <Fact label="Güncellemeler" value={license.updates_until ? `${formatDate(license.updates_until)} tarihine kadar` : null} warn={!license.updates_available} />
            <Fact label="Anahtar" value={license.key_hint} />
        </dl>
    );
}

SiteShow.layout = { breadcrumbs: [{ title: 'Siteler', href: dashboard() }] };
