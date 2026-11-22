import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ShopController from '@/actions/App/Http/Controllers/ShopController';
import Pagination from '@/components/pagination';
import ProductImage from '@/components/product-image';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';
import type { Paginated, Product } from '@/types';

export default function ShopIndex({ products, filters }: { products: Paginated<Product>; filters: { q?: string } }) {
    const [search, setSearch] = useState(filters.q ?? '');
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(
            () => router.get(ShopController.index.url(), search ? { q: search } : {}, { preserveState: true, replace: true }),
            300,
        );
        return () => clearTimeout(timer);
    }, [search]);

    return (
        <>
            <Head title="Taze kavrulmuş kahve" />
            <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold">Taze kavrulmuş kahve</h1>
                    <p className="text-muted-foreground">Her hafta kavrulur, aynı gün kargoya verilir.</p>
                </div>
                <div className="relative w-full max-w-xs">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Ürün ara" className="bg-white pl-8 dark:bg-neutral-900" />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                {products.data.map((product) => (
                    <Link key={product.id} href={ShopController.show(product.slug)} className="group rounded-xl border bg-white p-3 transition hover:shadow-md dark:bg-neutral-900">
                        <ProductImage product={product} />
                        <div className="mt-3 font-medium group-hover:underline">{product.name}</div>
                        <div className="mt-1 flex items-center justify-between text-sm">
                            <span className="font-semibold tabular-nums">{formatMoney(product.price)}</span>
                            {product.stock === 0 ? (
                                <span className="text-red-600">Tükendi</span>
                            ) : product.stock <= 3 ? (
                                <span className="text-amber-700">Son {product.stock} ürün</span>
                            ) : null}
                        </div>
                    </Link>
                ))}
            </div>
            {products.data.length === 0 && <p className="py-12 text-center text-muted-foreground">Aradığınız ürün bulunamadı.</p>}

            <div className="mt-8">
                <Pagination page={products} />
            </div>
        </>
    );
}
