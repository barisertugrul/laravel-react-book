import { Form } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CategoryOption, Product } from '@/types';

type Props = {
    categories: CategoryOption[];
    product?: Product;
};

/** Hem "yeni ürün" hem "ürünü düzenle" sayfasında kullanılan form. */
export default function ProductForm({ categories, product }: Props) {
    const [preview, setPreview] = useState<string | null>(
        product?.image_url ?? null,
    );

    const formProps = product
        ? ProductController.update.form(product.id)
        : ProductController.store.form();

    return (
        <Form {...formProps} className="max-w-xl space-y-5">
            {({ processing, errors, progress }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="category_id">Kategori</Label>
                        <Select
                            name="category_id"
                            defaultValue={
                                product ? String(product.category_id) : undefined
                            }
                        >
                            <SelectTrigger id="category_id">
                                <SelectValue placeholder="Kategori seçin" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.category_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Ürün adı</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={product?.name}
                            placeholder="ör. Izgara Köfte"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Açıklama</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows={3}
                            defaultValue={product?.description ?? ''}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Kısa, iştah açıcı bir açıklama"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="price">Fiyat (₺)</Label>
                            <Input
                                id="price"
                                name="price"
                                type="number"
                                step="0.01"
                                min={0}
                                defaultValue={product?.price}
                                placeholder="0,00"
                            />
                            <InputError message={errors.price} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sort_order">Sıra</Label>
                            <Input
                                id="sort_order"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={product?.sort_order ?? 0}
                            />
                            <InputError message={errors.sort_order} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="image">Görsel</Label>
                        <div className="flex items-center gap-4">
                            <div className="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted">
                                {preview ? (
                                    <img
                                        src={preview}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <ImageOff className="text-muted-foreground" />
                                )}
                            </div>
                            <Input
                                id="image"
                                name="image"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    setPreview(
                                        file
                                            ? URL.createObjectURL(file)
                                            : (product?.image_url ?? null),
                                    );
                                }}
                            />
                        </div>
                        {progress && (
                            <progress
                                value={progress.percentage}
                                max={100}
                                className="w-full"
                            />
                        )}
                        <InputError message={errors.image} />
                        {product?.image_url && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="remove_image"
                                    name="remove_image"
                                    value="1"
                                />
                                <Label htmlFor="remove_image">
                                    Mevcut görseli kaldır
                                </Label>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_available"
                            name="is_available"
                            value="1"
                            defaultChecked={product?.is_available ?? true}
                        />
                        <Label htmlFor="is_available">
                            Satışta (menüde göster)
                        </Label>
                    </div>

                    <Button disabled={processing}>
                        {product ? 'Değişiklikleri kaydet' : 'Ürünü ekle'}
                    </Button>
                </>
            )}
        </Form>
    );
}
