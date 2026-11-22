import { Form, Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { ComponentProps } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/CheckoutController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';
import type { Totals } from '@/types';

type Props = {
    lines: { name: string; quantity: number; total: number }[];
    totals: Totals;
    defaults: Record<'shipping_name' | 'shipping_phone' | 'shipping_address' | 'shipping_city', string>;
};

function Field({ name, label, error, ...props }: { name: string; label: string; error?: string } & ComponentProps<'input'>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} className="bg-white dark:bg-neutral-900" {...props} />
            <InputError message={error} />
        </div>
    );
}

export default function Checkout({ lines, totals, defaults }: Props) {
    return (
        <>
            <Head title="Siparişi tamamla" />
            <h1 className="mb-6 text-2xl font-semibold">Siparişi tamamla</h1>
            <Form {...CheckoutController.store.form()} className="grid gap-8 lg:grid-cols-3">
                {({ processing, errors }) => (
                    <>
                        <section className="space-y-4 lg:col-span-2">
                            <h2 className="font-medium">Teslimat adresi</h2>
                            {/* PlaceOrder'ın stok hatası: "X için yeterli stok kalmadı" */}
                            <InputError message={(errors as Record<string, string>).cart} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field name="shipping_name" label="Alıcı adı soyadı" defaultValue={defaults.shipping_name} error={errors.shipping_name} />
                                <Field name="shipping_phone" label="Telefon" type="tel" defaultValue={defaults.shipping_phone} placeholder="0532 123 45 67" error={errors.shipping_phone} />
                            </div>
                            <Field name="shipping_address" label="Adres" defaultValue={defaults.shipping_address} error={errors.shipping_address} />
                            <Field name="shipping_city" label="İl" defaultValue={defaults.shipping_city} className="max-w-xs bg-white dark:bg-neutral-900" error={errors.shipping_city} />
                        </section>

                        <aside className="h-fit space-y-2 rounded-xl border bg-white p-4 text-sm dark:bg-neutral-900">
                            <h2 className="mb-2 font-medium">Sipariş özeti</h2>
                            {lines.map((l, i) => (
                                <div key={i} className="flex justify-between gap-2">
                                    <span>{l.quantity} × {l.name}</span>
                                    <span className="tabular-nums">{formatMoney(l.total)}</span>
                                </div>
                            ))}
                            <div className="flex justify-between border-t pt-2">
                                <span className="text-muted-foreground">Kargo</span>
                                <span>{totals.shipping === 0 ? 'Bedava' : formatMoney(totals.shipping)}</span>
                            </div>
                            <div className="flex justify-between text-base font-semibold">
                                <span>Toplam</span>
                                <span className="tabular-nums">{formatMoney(totals.total)}</span>
                            </div>
                            <Button className="mt-2 w-full" size="lg" disabled={processing}>
                                <Lock /> Ödemeye geç
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                Ödeme sayfasına yönlendirileceksiniz. Ürünleriniz 30 dakika boyunca sizin için ayrılır.
                            </p>
                        </aside>
                    </>
                )}
            </Form>
        </>
    );
}
