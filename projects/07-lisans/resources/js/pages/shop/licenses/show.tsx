import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Globe } from 'lucide-react';
import { useState } from 'react';
import LicenseController from '@/actions/App/Http/Controllers/LicenseController';
import LicenseKey from '@/components/license-key';
import LicenseStatusBadge from '@/components/license-status-badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime, formatSites } from '@/lib/format';
import type { Activation, License } from '@/types';

function ActivationRow({ license, activation }: { license: License; activation: Activation }) {
    // Tarayıcının confirm() penceresi yerine satır içinde ikinci bir onay adımı
    const [confirming, setConfirming] = useState(false);

    const remove = () =>
        router.delete(LicenseController.destroyActivation.url({ license: license.id, activation: activation.id }), { preserveScroll: true });

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 p-4">
            <div className="flex items-center gap-3">
                <Globe className="size-5 text-muted-foreground" />
                <div>
                    <div className="font-medium">{activation.domain}</div>
                    <div className="text-xs text-muted-foreground">
                        Etkinleştirildi: {formatDate(activation.created_at)}
                        {activation.last_seen_at && <> · Son kontrol: {formatDateTime(activation.last_seen_at)}</>}
                    </div>
                </div>
            </div>
            {confirming ? (
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-muted-foreground">Bu sitede eklenti lisanssız kalacak.</span>
                    <Button size="sm" variant="destructive" onClick={remove}>Kaldır</Button>
                    <Button size="sm" variant="ghost" onClick={() => setConfirming(false)}>Vazgeç</Button>
                </div>
            ) : (
                <Button size="sm" variant="outline" onClick={() => setConfirming(true)}>Siteyi kaldır</Button>
            )}
        </li>
    );
}

export default function LicenseShow({ license, activations }: { license: License; activations: Activation[] }) {
    const used = activations.length;

    return (
        <>
            <Head title={license.plan.product.name} />
            <Link href={LicenseController.index()} className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft className="size-4" /> Lisanslarım
            </Link>

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold">{license.plan.product.name}</h1>
                <LicenseStatusBadge status={license.status} />
            </div>

            {license.status === 'revoked' && (
                <div className="mb-6 flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <AlertTriangle className="size-5 shrink-0" />
                    <div>
                        Bu lisans {license.revoked_at && formatDate(license.revoked_at)} tarihinde iptal edildi. Eklenti sitelerinizde artık lisanslı çalışmaz.
                        {license.revoke_reason && <div className="mt-1">Neden: {license.revoke_reason}</div>}
                    </div>
                </div>
            )}

            <section className="space-y-4 rounded-xl border bg-white p-5 dark:bg-neutral-900">
                <h2 className="font-medium">Lisans anahtarı</h2>
                {license.key && <LicenseKey value={license.key} />}
                <p className="text-sm text-muted-foreground">
                    WordPress panelinde <strong>Ayarlar → Lisans</strong> sayfasına yapıştırın. Anahtarı kimseyle paylaşmayın: anahtarı bilen, sitelerinizden birinin yerini kullanabilir.
                </p>
                <dl className="grid gap-4 border-t pt-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt className="text-muted-foreground">Plan</dt>
                        <dd className="font-medium">{license.plan.name}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Kullanılan</dt>
                        <dd className="font-medium">{used} / {formatSites(license.max_sites)}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Güncelleme ve destek</dt>
                        <dd className={license.has_updates ? 'font-medium' : 'font-medium text-amber-700 dark:text-amber-400'}>
                            {license.has_updates ? `${formatDate(license.updates_until)} tarihine kadar` : `${formatDate(license.updates_until)} tarihinde sona erdi`}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Sipariş</dt>
                        <dd className="font-mono font-medium">{license.order?.number ?? '—'}</dd>
                    </div>
                </dl>
            </section>

            <section className="mt-6">
                <h2 className="mb-3 font-medium">Siteler</h2>
                <ul className="divide-y rounded-xl border bg-white dark:bg-neutral-900">
                    {activations.map((a) => (
                        <ActivationRow key={a.id} license={license} activation={a} />
                    ))}
                    {activations.length === 0 && <li className="p-8 text-center text-muted-foreground">Bu lisans henüz hiçbir sitede etkinleştirilmedi.</li>}
                </ul>
            </section>
        </>
    );
}
