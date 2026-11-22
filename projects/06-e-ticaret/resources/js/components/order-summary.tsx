import OrderStatusBadge from '@/components/order-status-badge';
import { formatDateTime } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import type { Order } from '@/types';

/** Sipariş ayrıntısı: müşteri ve yönetici sayfalarında ortak */
export default function OrderSummary({ order }: { order: Order }) {
    return (
        <div className="grid gap-6 md:grid-cols-3">
            <div className="rounded-xl border bg-white md:col-span-2 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr className="border-b">
                            <th className="p-3 font-medium">Ürün</th>
                            <th className="p-3 text-right font-medium">Adet</th>
                            <th className="p-3 text-right font-medium">Birim fiyat</th>
                            <th className="p-3 text-right font-medium">Tutar</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {order.items?.map((item) => (
                            <tr key={item.id}>
                                <td className="p-3">{item.product_name}</td>
                                <td className="p-3 text-right">{item.quantity}</td>
                                <td className="p-3 text-right tabular-nums">{formatMoney(item.unit_price)}</td>
                                <td className="p-3 text-right tabular-nums">{formatMoney(item.total)}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="text-right">
                        <tr className="border-t">
                            <td colSpan={3} className="p-3 text-muted-foreground">Kargo</td>
                            <td className="p-3 tabular-nums">{order.shipping ? formatMoney(order.shipping) : 'Bedava'}</td>
                        </tr>
                        <tr className="font-semibold">
                            <td colSpan={3} className="p-3">Toplam</td>
                            <td className="p-3 tabular-nums">{formatMoney(order.total)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div className="space-y-4 text-sm">
                <div className="rounded-xl border bg-white p-4 dark:bg-neutral-900">
                    <div className="mb-2 flex items-center justify-between">
                        <span className="font-medium">Durum</span>
                        <OrderStatusBadge status={order.status} />
                    </div>
                    <dl className="space-y-1 text-muted-foreground">
                        <div>Sipariş: {formatDateTime(order.created_at)}</div>
                        {order.paid_at && <div>Ödeme: {formatDateTime(order.paid_at)}</div>}
                        {order.shipped_at && <div>Kargo: {formatDateTime(order.shipped_at)}</div>}
                        {order.tracking_number && <div className="font-medium text-foreground">Takip no: {order.tracking_number}</div>}
                    </dl>
                </div>
                <div className="rounded-xl border bg-white p-4 dark:bg-neutral-900">
                    <div className="mb-2 font-medium">Teslimat adresi</div>
                    <div>{order.shipping_name}</div>
                    <div className="text-muted-foreground">
                        {order.shipping_address}, {order.shipping_city}
                        <br />
                        {order.shipping_phone}
                    </div>
                </div>
            </div>
        </div>
    );
}
