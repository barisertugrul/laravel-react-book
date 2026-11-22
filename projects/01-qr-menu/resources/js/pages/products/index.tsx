import { Head, Link } from '@inertiajs/react';
import { ImageOff, Pencil, Plus, Trash2 } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/format';
import type { Product } from '@/types';

export default function ProductsIndex({ products }: { products: Product[] }) {
    return (
        <>
            <Head title="Ürünler" />
            <div className="space-y-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Ürünler"
                        description="Menüdeki tüm ürünler ve fiyatları."
                    />
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
                                <th className="p-3 font-medium">Kategori</th>
                                <th className="p-3 text-right font-medium">Fiyat</th>
                                <th className="p-3 font-medium">Durum</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {products.map((product) => (
                                <tr key={product.id}>
                                    <td className="p-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted">
                                                {product.image_url ? (
                                                    <img
                                                        src={product.image_url}
                                                        alt=""
                                                        className="size-full object-cover"
                                                    />
                                                ) : (
                                                    <ImageOff className="size-4 text-muted-foreground" />
                                                )}
                                            </div>
                                            <span className="font-medium">
                                                {product.name}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="p-3">{product.category?.name}</td>
                                    <td className="p-3 text-right tabular-nums">
                                        {formatPrice(product.price)}
                                    </td>
                                    <td className="p-3">
                                        {product.is_available ? (
                                            <Badge variant="secondary">Satışta</Badge>
                                        ) : (
                                            <Badge variant="outline">Tükendi</Badge>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
                                            <Button size="icon" variant="ghost" asChild>
                                                <Link
                                                    href={ProductController.edit(product.id)}
                                                    aria-label="Düzenle"
                                                >
                                                    <Pencil />
                                                </Link>
                                            </Button>
                                            <Button size="icon" variant="ghost" asChild>
                                                <Link
                                                    href={ProductController.destroy(product.id)}
                                                    as="button"
                                                    aria-label="Sil"
                                                    preserveScroll
                                                    onBefore={() =>
                                                        confirm(`"${product.name}" silinsin mi?`)
                                                    }
                                                >
                                                    <Trash2 className="text-destructive" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Ürünler', href: ProductController.index() }],
};
