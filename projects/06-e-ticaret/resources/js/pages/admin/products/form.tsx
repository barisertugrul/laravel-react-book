import { Form, Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Product } from '@/types';

function Field({ name, label, error, hint, ...props }: { name: string; label: string; error?: string; hint?: string } & ComponentProps<'input'>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} {...props} />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

/** Kuruşu forma yazılacak metne çevirir: 125050 → "1250,50" */
const toInput = (cents: number) => `${Math.trunc(cents / 100)},${String(cents % 100).padStart(2, '0')}`;

export default function ProductForm({ product }: { product?: Product }) {
    const action = product ? ProductController.update.form(product.slug) : ProductController.store.form();

    return (
        <>
            <Head title={product ? product.name : 'Yeni ürün'} />
            <div className="p-4">
                <Heading title={product ? product.name : 'Yeni ürün'} />
                {/* İşaretsiz bir checkbox forma hiç eklenmez; transform ile her zaman true/false gönderiyoruz */}
                <Form {...action} transform={(data) => ({ ...data, is_active: data.is_active === '1' })} className="grid max-w-2xl gap-4 sm:grid-cols-2">
                    {({ processing, errors }) => (
                        <>
                            <div className="sm:col-span-2">
                                <Field name="name" label="Ürün adı" defaultValue={product?.name} error={errors.name} />
                            </div>
                            <Field name="slug" label="Adres (slug)" defaultValue={product?.slug} hint="Boş bırakırsanız addan üretilir." error={errors.slug} />
                            <div />
                            <Field name="price" label="Fiyat (₺, KDV dahil)" inputMode="decimal" defaultValue={product ? toInput(product.price) : ''} error={errors.price} />
                            <Field name="stock" label="Stok" type="number" min={0} defaultValue={product?.stock ?? 0} error={errors.stock} />
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="description">Açıklama</Label>
                                <textarea id="description" name="description" rows={3} defaultValue={product?.description ?? ''} className="rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                                <InputError message={errors.description} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox name="is_active" value="1" defaultChecked={product?.is_active ?? true} />
                                Satışta
                            </label>
                            <div className="sm:col-span-2">
                                <Button disabled={processing}>{product ? 'Kaydet' : 'Ürünü oluştur'}</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ProductForm.layout = { breadcrumbs: [{ title: 'Ürünler', href: ProductController.index() }] };
