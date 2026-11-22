import { Head, Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import OrderStatusBadge from '@/components/order-status-badge';
import Pagination from '@/components/pagination';
import { formatDateTime } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import type { Order, Paginated } from '@/types';

export default function MyOrders({ orders }: { orders: Paginated<Order> }) {
    return (
        <>
            <Head title="Siparişlerim" />
            <h1 className="mb-6 text-2xl font-semibold">Siparişlerim</h1>
            <ul className="divide-y rounded-xl border bg-white dark:bg-neutral-900">
                {orders.data.map((order) => (
                    <li key={order.id}>
                        <Link href={OrderController.show(order.number)} className="flex flex-wrap items-center justify-between gap-4 p-4 hover:bg-muted/40">
                            <div>
                                <div className="font-mono font-semibold">{order.number}</div>
                                <div className="text-sm text-muted-foreground">
                                    {formatDateTime(order.created_at)} · {order.items_count} ürün
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <span className="font-medium tabular-nums">{formatMoney(order.total)}</span>
                                <OrderStatusBadge status={order.status} />
                            </div>
                        </Link>
                    </li>
                ))}
                {orders.data.length === 0 && <li className="p-8 text-center text-muted-foreground">Henüz siparişiniz yok.</li>}
            </ul>
            <div className="mt-6">
                <Pagination page={orders} />
            </div>
        </>
    );
}
