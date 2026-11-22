import { Head } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import ProductForm from '@/components/product-form';
import type { CategoryOption } from '@/types';

export default function ProductsCreate({
    categories,
}: {
    categories: CategoryOption[];
}) {
    return (
        <>
            <Head title="Yeni ürün" />
            <div className="p-4">
                <Heading title="Yeni ürün" description="Menünüze bir ürün ekleyin." />
                <ProductForm categories={categories} />
            </div>
        </>
    );
}

ProductsCreate.layout = {
    breadcrumbs: [
        { title: 'Ürünler', href: ProductController.index() },
        { title: 'Yeni ürün', href: ProductController.create() },
    ],
};
