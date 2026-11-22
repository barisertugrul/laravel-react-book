import { Form, Head, Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/CartController';
import ShopController from '@/actions/App/Http/Controllers/ShopController';
import InputError from '@/components/input-error';
import ProductImage from '@/components/product-image';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';
import type { Product } from '@/types';

export default function ShopProduct({ product }: { product: Product }) {
    const soldOut = product.stock === 0;

    return (
        <>
            <Head title={product.name} />
            <Link href={ShopController.index()} className="text-sm text-muted-foreground hover:underline">
                ← Tüm ürünler
            </Link>
            <div className="mt-4 grid gap-8 md:grid-cols-2">
                <ProductImage product={product} className="text-6xl" />
                <div className="space-y-4">
                    <h1 className="text-3xl font-semibold">{product.name}</h1>
                    <div className="text-2xl font-semibold tabular-nums">{formatMoney(product.price)}</div>
                    {product.description && <p className="text-muted-foreground">{product.description}</p>}

                    {soldOut ? (
                        <p className="font-medium text-red-600">Bu ürün şu anda tükendi.</p>
                    ) : (
                        // Formda yalnızca ürün id'si ve adet var: fiyat sunucuda, veritabanından okunur
                        <Form {...CartController.store.form()} options={{ preserveScroll: true }} className="flex items-start gap-3">
                            {({ processing, errors }) => (
                                <>
                                    <input type="hidden" name="product_id" value={product.id} />
                                    <div>
                                        <Input name="quantity" type="number" min={1} max={Math.min(product.stock, 20)} defaultValue={1} className="w-20 bg-white dark:bg-neutral-900" aria-label="Adet" />
                                        <InputError message={errors.quantity} />
                                    </div>
                                    <Button disabled={processing} size="lg">
                                        <ShoppingBag /> Sepete ekle
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                    {!soldOut && product.stock <= 3 && <p className="text-sm text-amber-700">Stokta yalnızca {product.stock} adet kaldı.</p>}
                </div>
            </div>
        </>
    );
}
