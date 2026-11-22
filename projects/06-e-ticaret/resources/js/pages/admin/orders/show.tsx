import { Form, Head } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import OrderSummary from '@/components/order-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Order } from '@/types';

export default function AdminOrder({ order }: { order: Order }) {
    return (
        <>
            <Head title={order.number} />
            <div className="space-y-6 p-4">
                <Heading
                    title={order.number}
                    description={`${order.user?.name} · ${order.user?.email} · ${order.payment_provider}${order.payment_reference ? ` (${order.payment_reference})` : ''}`}
                />

                {order.status === 'paid' && (
                    <Form {...OrderController.ship.form(order.number)} className="flex max-w-lg items-start gap-2 rounded-xl border p-4">
                        {({ processing, errors }) => (
                            <>
                                <div className="flex-1">
                                    <Input name="tracking_number" placeholder="Kargo takip numarası" aria-label="Kargo takip numarası" />
                                    <InputError message={errors.tracking_number} />
                                </div>
                                <Button disabled={processing}>
                                    <Truck /> Kargoya verildi
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <OrderSummary order={order} />
            </div>
        </>
    );
}

AdminOrder.layout = { breadcrumbs: [{ title: 'Siparişler', href: OrderController.index() }] };
