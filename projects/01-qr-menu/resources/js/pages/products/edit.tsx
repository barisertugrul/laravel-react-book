import { Head } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import ProductForm from '@/components/product-form';
import type { CategoryOption, Product } from '@/types';

export default function ProductsEdit({
    product,
    categories,
}: {
    product: Product;
    categories: CategoryOption[];
}) {
    return (
        <>
            <Head title={`${product.name} düzenle`} />
            <div className="p-4">
                <Heading title="Ürünü düzenle" description={product.name} />
                <ProductForm categories={categories} product={product} />
            </div>
        </>
    );
}

ProductsEdit.layout = {
    breadcrumbs: [
        { title: 'Ürünler', href: ProductController.index() },
        { title: 'Düzenle', href: ProductController.index() },
    ],
};
