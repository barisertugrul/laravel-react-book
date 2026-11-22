import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Paginated, Product } from '@/types';

export default function AdminProducts({ products }: { products: Paginated<Product> }) {
    return (
        <>
            <Head title="Ürünler" />
            <div className="space-y-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Ürünler" description="Fiyat, stok ve satış durumu" />
                    <Button asChild>
                        <Link href={ProductController.create()}>
                            <Plus /> Yeni ürün
                        </Link>
                    </Button>
                </div>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Ürün</th>
                                <th className="p-3 text-right font-medium">Fiyat</th>
                                <th className="p-3 text-right font-medium">Stok</th>
                                <th className="p-3 font-medium">Durum</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {products.data.map((p) => (
                                <tr key={p.id} className="hover:bg-muted/30">
                                    <td className="p-3">
                                        <Link href={ProductController.edit(p.slug)} className="font-medium underline-offset-4 hover:underline">
                                            {p.name}
                                        </Link>
                                    </td>
                                    <td className="p-3 text-right tabular-nums">{formatMoney(p.price)}</td>
                                    <td className={cn('p-3 text-right tabular-nums', p.stock === 0 && 'font-medium text-red-600', p.stock > 0 && p.stock <= 3 && 'text-amber-700')}>
                                        {p.stock}
                                    </td>
                                    <td className="p-3">{p.is_active ? 'Satışta' : <span className="text-muted-foreground">Satış dışı</span>}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={products} />
            </div>
        </>
    );
}

AdminProducts.layout = { breadcrumbs: [{ title: 'Ürünler', href: ProductController.index() }] };
