import { Head, Link, usePoll } from '@inertiajs/react';
import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import { useEffect } from 'react';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import ShopController from '@/actions/App/Http/Controllers/ShopController';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import type { Order } from '@/types';

/**
 * Ödemeden dönüş. Bu sayfa siparişin sonucunu BELİRLEMEZ, yalnızca izler:
 * ödeme bildirimi (webhook) gelene kadar 2 saniyede bir yalnızca "order" prop'unu yeniden ister.
 */
export default function PaymentResult({ order }: { order: Pick<Order, 'number' | 'total' | 'status'> }) {
    const { stop } = usePoll(2000, { only: ['order'] });

    useEffect(() => {
        if (order.status !== 'pending') {
            stop();
        }
    }, [order.status, stop]);

    return (
        <>
            <Head title="Ödeme sonucu" />
            <div className="mx-auto max-w-md rounded-xl border bg-white p-8 text-center dark:bg-neutral-900">
                {order.status === 'pending' && (
                    <>
                        <Loader2 className="mx-auto size-12 animate-spin text-amber-600" />
                        <h1 className="mt-4 text-xl font-semibold">Ödemeniz onaylanıyor…</h1>
                        <p className="mt-2 text-muted-foreground">Bu birkaç saniye sürebilir. Sayfayı kapatmayın.</p>
                    </>
                )}
                {(order.status === 'paid' || order.status === 'shipped') && (
                    <>
                        <CheckCircle2 className="mx-auto size-12 text-emerald-600" />
                        <h1 className="mt-4 text-xl font-semibold">Siparişiniz alındı</h1>
                        <p className="mt-2 text-muted-foreground">
                            {order.number} numaralı siparişinizin ödemesi ({formatMoney(order.total)}) onaylandı. E-posta adresinize bir özet gönderdik.
                        </p>
                        <Button asChild className="mt-6">
                            <Link href={OrderController.show(order.number)}>Siparişi görüntüle</Link>
                        </Button>
                    </>
                )}
                {order.status === 'cancelled' && (
                    <>
                        <XCircle className="mx-auto size-12 text-red-600" />
                        <h1 className="mt-4 text-xl font-semibold">Ödeme tamamlanamadı</h1>
                        <p className="mt-2 text-muted-foreground">Kartınızdan para çekilmedi. Ürünleri yeniden sepete ekleyip tekrar deneyebilirsiniz.</p>
                        <Button asChild className="mt-6">
                            <Link href={ShopController.index()}>Mağazaya dön</Link>
                        </Button>
                    </>
                )}
            </div>
        </>
    );
}
