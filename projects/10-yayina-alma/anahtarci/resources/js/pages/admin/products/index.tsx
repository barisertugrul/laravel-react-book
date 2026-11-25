import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { Product } from '@/types';

export default function AdminProducts({ products }: { products: Product[] }) {
    return (
        <>
            <Head title="Ürünler" />
            <div className="space-y-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Ürünler" description="Satılan eklentiler ve planları" />
                    <Button asChild>
                        <Link href={ProductController.create()}>
                            <Plus /> Yeni ürün
                        </Link>
                    </Button>
                </div>
                {products.length === 0 ? (
                    <p className="rounded-xl border p-6 text-sm text-muted-foreground">
                        Henüz ürün yok. Vitrinde bir şey satılabilmesi için bir ürün ve en az bir plan ekleyin.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Ürün</th>
                                    <th className="p-3 font-medium">Adres (slug)</th>
                                    <th className="p-3 text-right font-medium">Plan</th>
                                    <th className="p-3 text-right font-medium">Satılan lisans</th>
                                    <th className="p-3 font-medium">Durum</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {products.map((p) => (
                                    <tr key={p.id} data-test="product-row" className="hover:bg-muted/30">
                                        <td className="p-3">
                                            <Link href={ProductController.edit(p.id)} className="font-medium underline-offset-4 hover:underline">
                                                {p.name}
                                            </Link>
                                        </td>
                                        <td className="p-3 font-mono text-xs">{p.slug}</td>
                                        <td className="p-3 text-right tabular-nums">
                                            {p.plans_count === 0 ? <span className="text-amber-700">plan yok</span> : p.plans_count}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">{p.licenses_count}</td>
                                        <td className="p-3">{p.is_active ? 'Satışta' : <span className="text-muted-foreground">Satış dışı</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

AdminProducts.layout = { breadcrumbs: [{ title: 'Ürünler', href: ProductController.index() }] };
