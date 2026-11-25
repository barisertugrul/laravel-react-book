import { Form, Head } from '@inertiajs/react';
import { Ban, CalendarPlus } from 'lucide-react';
import LicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
import { eventLabel } from '@/components/event-label';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import LicenseStatusBadge from '@/components/license-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatDateTime, formatSites } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Activation, License, LicenseEvent } from '@/types';

type Props = { license: License; activations: Activation[]; events: LicenseEvent[] };

export default function AdminLicenseShow({ license, activations, events }: Props) {
    return (
        <>
            <Head title={`Lisans #${license.id}`} />
            <div className="grid gap-6 p-4 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <div className="flex flex-wrap items-center gap-3">
                        <Heading title={`Lisans #${license.id} · ${license.plan.product.name}`} description={`${license.user?.name} · ${license.user?.email}`} />
                        <LicenseStatusBadge status={license.status} />
                    </div>

                    <dl className="grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-4">
                        <div>
                            <dt className="text-muted-foreground">Plan</dt>
                            <dd className="font-medium">{license.plan.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Site</dt>
                            <dd className="font-medium">{activations.length} / {formatSites(license.max_sites)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Güncelleme</dt>
                            <dd className={cn('font-medium', !license.has_updates && 'text-amber-700 dark:text-amber-400')}>{formatDate(license.updates_until)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Sipariş</dt>
                            <dd className="font-medium">
                                {license.order ? (
                                    <>
                                        <span className="block font-mono">{license.order.number}</span>
                                        <span className="text-muted-foreground tabular-nums">{formatMoney(license.order.total)}</span>
                                    </>
                                ) : (
                                    '—'
                                )}
                            </dd>
                        </div>
                    </dl>

                    <section>
                        <h2 className="mb-2 font-medium">Siteler</h2>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Alan adı</th>
                                        <th className="p-3 font-medium">IP</th>
                                        <th className="p-3 font-medium">Etkinleştirme</th>
                                        <th className="p-3 font-medium">Son kontrol</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {activations.map((a) => (
                                        <tr key={a.id}>
                                            <td className="p-3 font-medium">{a.domain}</td>
                                            <td className="p-3 font-mono text-xs">{a.ip ?? '—'}</td>
                                            <td className="p-3 whitespace-nowrap">{formatDate(a.created_at)}</td>
                                            <td className="p-3 whitespace-nowrap">{a.last_seen_at ? formatDateTime(a.last_seen_at) : '—'}</td>
                                        </tr>
                                    ))}
                                    {activations.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="p-6 text-center text-muted-foreground">Etkin site yok.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section>
                        <h2 className="mb-2 font-medium">Geçmiş</h2>
                        <ol className="space-y-3 rounded-xl border p-4 text-sm">
                            {events.map((e) => (
                                <li key={e.id} className="flex flex-wrap gap-x-3">
                                    <span className="w-36 shrink-0 text-muted-foreground">{formatDateTime(e.created_at)}</span>
                                    <span className={cn('font-medium', (e.type === 'rejected' || e.type === 'revoked') && 'text-red-700 dark:text-red-400')}>{eventLabel(e)}</span>
                                    {e.domain && <span>{e.domain}</span>}
                                    {e.ip && <span className="font-mono text-xs text-muted-foreground">{e.ip}</span>}
                                    {e.meta?.reason && e.type === 'revoked' && <span className="text-muted-foreground">“{e.meta.reason}”</span>}
                                </li>
                            ))}
                        </ol>
                    </section>
                </div>

                <aside className="h-fit space-y-4">
                    <div className="space-y-3 rounded-xl border p-4">
                        <h2 className="font-medium">Güncelleme süresi</h2>
                        <p className="text-sm text-muted-foreground">Yenileme ödemesinden sonra süreyi 12 ay uzatın.</p>
                        <Form {...LicenseController.extend.form(license.id)} options={{ preserveScroll: true }}>
                            {({ processing }) => (
                                <Button variant="outline" disabled={processing} className="w-full">
                                    <CalendarPlus /> 12 ay uzat
                                </Button>
                            )}
                        </Form>
                    </div>

                    {license.status === 'active' ? (
                        <div className="space-y-3 rounded-xl border border-red-200 p-4 dark:border-red-900">
                            <h2 className="font-medium text-red-700 dark:text-red-400">Lisansı iptal et</h2>
                            <p className="text-sm text-muted-foreground">İade veya kötüye kullanımda. Siteler bir sonraki kontrolde “geçersiz” yanıtı alır.</p>
                            <Form {...LicenseController.revoke.form(license.id)} options={{ preserveScroll: true }} className="space-y-2">
                                {({ processing, errors }) => (
                                    <>
                                        <Input name="reason" placeholder="Neden (ör. iade, sipariş LIS-000003)" />
                                        <InputError message={errors.reason} />
                                        <Button variant="destructive" disabled={processing} className="w-full">
                                            <Ban /> İptal et
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    ) : (
                        <div className="rounded-xl border p-4 text-sm">
                            <div className="font-medium">İptal edildi</div>
                            <div className="text-muted-foreground">
                                {license.revoked_at && formatDateTime(license.revoked_at)} · {license.revoke_reason}
                            </div>
                        </div>
                    )}
                </aside>
            </div>
        </>
    );
}

AdminLicenseShow.layout = { breadcrumbs: [{ title: 'Lisanslar', href: LicenseController.index() }] };
