import { Head, Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import Heading from '@/components/heading';
import { statusLabel } from '@/components/order-status-badge';
import Pagination from '@/components/pagination';
import { formatDateTime } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Order, OrderStatus, Paginated } from '@/types';

type Props = { orders: Paginated<Order>; status: OrderStatus; counts: Partial<Record<OrderStatus, number>> };

const tabs: OrderStatus[] = ['paid', 'shipped', 'pending', 'cancelled'];

export default function AdminOrders({ orders, status, counts }: Props) {
    return (
        <>
            <Head title="Siparişler" />
            <div className="space-y-4 p-4">
                <Heading title="Siparişler" description="Ödenen siparişleri hazırlayıp kargoya verin" />
                <div className="flex flex-wrap gap-1">
                    {tabs.map((s) => (
                        <Link
                            key={s}
                            href={OrderController.index({ query: { status: s } })}
                            className={cn('rounded-full border px-3 py-1 text-sm', s === status ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-accent')}
                        >
                            {statusLabel(s)} <span className="ml-1 opacity-70">{counts[s] ?? 0}</span>
                        </Link>
                    ))}
                </div>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Sipariş</th>
                                <th className="p-3 font-medium">Müşteri</th>
                                <th className="p-3 font-medium">Tarih</th>
                                <th className="p-3 text-right font-medium">Ürün</th>
                                <th className="p-3 text-right font-medium">Toplam</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {orders.data.map((o) => (
                                <tr key={o.id} className="hover:bg-muted/30">
                                    <td className="p-3 font-mono">
                                        <Link href={OrderController.show(o.number)} className="font-semibold underline-offset-4 hover:underline">
                                            {o.number}
                                        </Link>
                                    </td>
                                    <td className="p-3">{o.user?.name}</td>
                                    <td className="p-3 whitespace-nowrap">{formatDateTime(o.created_at)}</td>
                                    <td className="p-3 text-right">{o.items_count}</td>
                                    <td className="p-3 text-right tabular-nums">{formatMoney(o.total)}</td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="p-8 text-center text-muted-foreground">Bu durumda sipariş yok.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination page={orders} />
            </div>
        </>
    );
}

AdminOrders.layout = { breadcrumbs: [{ title: 'Siparişler', href: OrderController.index() }] };
