import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Category } from '@/types';

export default function CategoriesIndex({
    categories,
}: {
    categories: Category[];
}) {
    // Düzenlenen kategori; null ise form "yeni kategori" modundadır.
    const [editing, setEditing] = useState<Category | null>(null);

    const formProps = editing
        ? CategoryController.update.form(editing.id)
        : CategoryController.store.form();

    return (
        <>
            <Head title="Kategoriler" />
            <div className="flex flex-1 flex-col gap-6 p-4 lg:flex-row lg:items-start">
                <Card className="lg:w-80 lg:shrink-0">
                    <CardHeader>
                        <CardTitle>
                            {editing ? 'Kategoriyi düzenle' : 'Yeni kategori'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            key={editing?.id ?? 'new'}
                            {...formProps}
                            resetOnSuccess
                            onSuccess={() => setEditing(null)}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Ad</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={editing?.name}
                                            placeholder="ör. Kahvaltı"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="sort_order">Sıra</Label>
                                        <Input
                                            id="sort_order"
                                            name="sort_order"
                                            type="number"
                                            min={0}
                                            defaultValue={editing?.sort_order ?? 0}
                                        />
                                        <InputError message={errors.sort_order} />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="is_active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={editing?.is_active ?? true}
                                        />
                                        <Label htmlFor="is_active">
                                            Menüde göster
                                        </Label>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button disabled={processing}>
                                            {editing ? 'Güncelle' : 'Ekle'}
                                        </Button>
                                        {editing && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() => setEditing(null)}
                                            >
                                                <X /> Vazgeç
                                            </Button>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="flex-1 space-y-4">
                    <Heading
                        title="Kategoriler"
                        description="Menünüz bu sırayla gösterilir."
                    />
                    {categories.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Henüz kategori yok. Soldaki formdan ilkini ekleyin.
                        </p>
                    )}
                    <ul className="divide-y rounded-xl border">
                        {categories.map((category) => (
                            <li
                                key={category.id}
                                className="flex items-center gap-3 p-3"
                            >
                                <span className="w-8 text-center text-sm text-muted-foreground">
                                    {category.sort_order}
                                </span>
                                <span className="flex-1 font-medium">
                                    {category.name}
                                </span>
                                <Badge variant="secondary">
                                    {category.products_count} ürün
                                </Badge>
                                {!category.is_active && (
                                    <Badge variant="outline">Gizli</Badge>
                                )}
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Düzenle"
                                    onClick={() => setEditing(category)}
                                >
                                    <Pencil />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Sil"
                                    asChild
                                >
                                    <Link
                                        href={CategoryController.destroy(category.id)}
                                        as="button"
                                        preserveScroll
                                        onBefore={() =>
                                            confirm(
                                                `"${category.name}" ve içindeki ürünler silinsin mi?`,
                                            )
                                        }
                                    >
                                        <Trash2 className="text-destructive" />
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Kategoriler', href: CategoryController.index() }],
};
