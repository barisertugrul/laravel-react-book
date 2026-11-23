import { Head, Link, usePoll } from '@inertiajs/react';
import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import { useEffect } from 'react';
import LicenseController from '@/actions/App/Http/Controllers/LicenseController';
import LicenseKey from '@/components/license-key';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { home } from '@/routes';
import type { OrderStatus } from '@/types';

type Props = {
    order: { number: string; status: OrderStatus; total: number; product: string; plan: string };
    license: { id: number; key: string } | null;
};

/**
 * Ödemeden dönüş (Bölüm 6'daki sayfanın lisans sürümü). İki aşamayı izler:
 * 1. webhook gelir, sipariş ödenir  2. kuyruktaki iş lisansı üretir.
 * Lisans gelene (veya sipariş iptal olana) kadar 2 saniyede bir yalnızca bu iki prop yenilenir.
 */
export default function PurchaseResult({ order, license }: Props) {
    const { stop } = usePoll(2000, { only: ['order', 'license'] });
    const done = license !== null || order.status === 'cancelled';

    useEffect(() => {
        if (done) {
            stop();
        }
    }, [done, stop]);

    return (
        <>
            <Head title="Satın alma sonucu" />
            <div className="mx-auto max-w-xl rounded-xl border bg-white p-8 text-center dark:bg-neutral-900">
                {!done && (
                    <>
                        <Loader2 className="mx-auto size-12 animate-spin text-indigo-600" />
                        <h1 className="mt-4 text-xl font-semibold">{order.status === 'paid' ? 'Lisansınız hazırlanıyor…' : 'Ödemeniz onaylanıyor…'}</h1>
                        <p className="mt-2 text-muted-foreground">Bu birkaç saniye sürebilir. Sayfayı kapatmayın.</p>
                    </>
                )}
                {license && (
                    <>
                        <CheckCircle2 className="mx-auto size-12 text-emerald-600" />
                        <h1 className="mt-4 text-xl font-semibold">Lisansınız hazır</h1>
                        <p className="mt-2 text-muted-foreground">
                            {order.product} · {order.plan} ({formatMoney(order.total)}). Anahtarı e-postanıza da gönderdik.
                        </p>
                        <div className="mt-6 text-left">
                            <LicenseKey value={license.key} />
                        </div>
                        <Button asChild className="mt-6">
                            <Link href={LicenseController.show(license.id)}>Lisansı yönet</Link>
                        </Button>
                    </>
                )}
                {order.status === 'cancelled' && (
                    <>
                        <XCircle className="mx-auto size-12 text-red-600" />
                        <h1 className="mt-4 text-xl font-semibold">Ödeme tamamlanamadı</h1>
                        <p className="mt-2 text-muted-foreground">Kartınızdan para çekilmedi. Dilediğiniz zaman tekrar deneyebilirsiniz.</p>
                        <Button asChild className="mt-6">
                            <Link href={home()}>Eklentilere dön</Link>
                        </Button>
                    </>
                )}
            </div>
        </>
    );
}
