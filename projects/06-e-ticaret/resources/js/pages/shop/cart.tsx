import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/CartController';
import CheckoutController from '@/actions/App/Http/Controllers/CheckoutController';
import ShopController from '@/actions/App/Http/Controllers/ShopController';
import ProductImage from '@/components/product-image';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import type { CartLine, Totals } from '@/types';

type Props = { lines: CartLine[]; totals: Totals; freeShippingFrom: number };

export default function Cart({ lines, totals, freeShippingFrom }: Props) {
    const hasStockProblem = lines.some((l) => !l.in_stock);

    function setQuantity(line: CartLine, quantity: number) {
        router.patch(CartController.update.url(line.product.id), { quantity }, { preserveScroll: true });
    }

    if (lines.length === 0) {
        return (
            <>
                <Head title="Sepet" />
                <div className="py-16 text-center">
                    <h1 className="text-2xl font-semibold">Sepetiniz boş</h1>
                    <Button asChild className="mt-6">
                        <Link href={ShopController.index()}>Alışverişe başla</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Sepet" />
            <h1 className="mb-6 text-2xl font-semibold">Sepet</h1>
            <div className="grid gap-8 lg:grid-cols-3">
                <ul className="divide-y rounded-xl border bg-white lg:col-span-2 dark:bg-neutral-900">
                    {lines.map((line) => (
                        <li key={line.product.id} className="flex items-center gap-4 p-4">
                            <ProductImage product={line.product} className="size-16 text-lg" />
                            <div className="flex-1">
                                <Link href={ShopController.show(line.product.slug)} className="font-medium hover:underline">
                                    {line.product.name}
                                </Link>
                                <div className="text-sm text-muted-foreground">{formatMoney(line.product.price)}</div>
                                {!line.in_stock && <div className="text-sm text-red-600">Stokta {line.product.stock} adet var; adedi azaltın.</div>}
                            </div>
                            <select
                                value={line.quantity}
                                onChange={(e) => setQuantity(line, Number(e.target.value))}
                                className="h-9 rounded-md border px-2 text-sm"
                                aria-label="Adet"
                            >
                                {Array.from({ length: Math.max(line.quantity, Math.min(line.product.stock, 20)) }, (_, i) => i + 1).map((n) => (
                                    <option key={n} value={n}>{n}</option>
                                ))}
                            </select>
                            <div className="w-28 text-right font-medium tabular-nums">{formatMoney(line.total)}</div>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Kaldır"
                                onClick={() => router.delete(CartController.destroy.url(line.product.id), { preserveScroll: true })}
                            >
                                <Trash2 />
                            </Button>
                        </li>
                    ))}
                </ul>

                <div className="h-fit space-y-2 rounded-xl border bg-white p-4 text-sm dark:bg-neutral-900">
                    <Row label="Ara toplam" value={formatMoney(totals.subtotal)} />
                    <Row label="Kargo" value={totals.shipping === 0 ? 'Bedava' : formatMoney(totals.shipping)} />
                    {totals.shipping > 0 && (
                        <p className="text-xs text-amber-700">{formatMoney(freeShippingFrom - totals.subtotal)} daha ekleyin, kargo bedava olsun.</p>
                    )}
                    <div className="flex justify-between border-t pt-2 text-base font-semibold">
                        <span>Toplam</span>
                        <span className="tabular-nums">{formatMoney(totals.total)}</span>
                    </div>
                    <Button asChild={!hasStockProblem} disabled={hasStockProblem} className="mt-2 w-full" size="lg">
                        {hasStockProblem ? <span>Önce stok sorununu düzeltin</span> : <Link href={CheckoutController.create()}>Siparişi tamamla</Link>}
                    </Button>
                </div>
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}
