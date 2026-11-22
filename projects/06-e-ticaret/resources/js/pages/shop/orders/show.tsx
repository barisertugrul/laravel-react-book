import { Head, Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import OrderSummary from '@/components/order-summary';
import type { Order } from '@/types';

export default function MyOrder({ order }: { order: Order }) {
    return (
        <>
            <Head title={order.number} />
            <Link href={OrderController.index()} className="text-sm text-muted-foreground hover:underline">
                ← Siparişlerim
            </Link>
            <h1 className="mt-2 mb-6 font-mono text-2xl font-semibold">{order.number}</h1>
            <OrderSummary order={order} />
        </>
    );
}
