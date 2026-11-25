import { Form, Head } from '@inertiajs/react';
import { Lock, Trash2 } from 'lucide-react';
import type { ComponentProps } from 'react';
import PlanController from '@/actions/App/Http/Controllers/Admin/PlanController';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatSites } from '@/lib/format';
import { formatMoney, toMoneyInput } from '@/lib/money';
import type { Plan, Product } from '@/types';

function Field({ name, label, error, hint, ...props }: { name: string; label: string; error?: string; hint?: string } & ComponentProps<'input'>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={props.id ?? name}>{label}</Label>
            <Input id={props.id ?? name} name={name} {...props} />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

/** Bir planın alanları: hem "plan ekle" hem her plan satırı kullanır */
function PlanFields({ plan, errors, prefix }: { plan?: Plan; errors: Record<string, string>; prefix: string }) {
    return (
        <>
            <Field id={`${prefix}-name`} name="name" label="Plan adı" placeholder="5 site" defaultValue={plan?.name} error={errors.name} />
            <Field id={`${prefix}-price`} name="price" label="Fiyat (₺)" inputMode="decimal" placeholder="1490,00" defaultValue={plan ? toMoneyInput(plan.price) : ''} error={errors.price} />
            <Field id={`${prefix}-max_sites`} name="max_sites" label="Site sınırı" type="number" min={1} placeholder="sınırsız" defaultValue={plan?.max_sites ?? ''} hint="Boş: sınırsız" error={errors.max_sites} />
            <Field id={`${prefix}-sort`} name="sort" label="Sıra" type="number" min={0} defaultValue={plan?.sort ?? 0} error={errors.sort} />
        </>
    );
}

function PlanRow({ plan }: { plan: Plan }) {
    const sold = (plan.orders_count ?? 0) > 0;

    return (
        <div data-test="plan-row" className="rounded-xl border p-4">
            <div className="mb-3 flex items-center justify-between text-sm">
                <span className="font-medium">
                    {plan.name} · {formatMoney(plan.price)} · {formatSites(plan.max_sites)}
                </span>
                <span className="text-muted-foreground">{plan.licenses_count} lisans satıldı</span>
            </div>
            {/* errorBag: sayfadaki her formun hataları ayrı (aynı alan adları birbirine karışmasın) */}
            <Form {...PlanController.update.form(plan.id)} errorBag={`plan-${plan.id}`} options={{ preserveScroll: true }} className="grid gap-3 sm:grid-cols-5">
                {({ processing, errors }) => (
                    <>
                        <PlanFields plan={plan} errors={errors} prefix={`plan-${plan.id}`} />
                        <div className="flex items-end">
                            <Button variant="outline" disabled={processing}>
                                Kaydet
                            </Button>
                        </div>
                    </>
                )}
            </Form>
            <div className="mt-3 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>Fiyatı ya da site sınırını değiştirmek daha önce satılan lisansları etkilemez: tutar siparişe, sınır lisansa kopyalandı.</span>
                {sold ? (
                    <span className="flex shrink-0 items-center gap-1">
                        <Lock className="size-3" /> Sipariş var, silinemez
                    </span>
                ) : (
                    <Form {...PlanController.destroy.form(plan.id)} errorBag={`plan-${plan.id}-sil`} options={{ preserveScroll: true }}>
                        {({ processing, errors }) => (
                            <>
                                <Button variant="ghost" size="sm" disabled={processing} className="text-red-600">
                                    <Trash2 /> Planı sil
                                </Button>
                                <InputError message={errors.plan} />
                            </>
                        )}
                    </Form>
                )}
            </div>
        </div>
    );
}

export default function ProductForm({ product, plans = [] }: { product?: Product; plans?: Plan[] }) {
    const action = product ? ProductController.update.form(product.id) : ProductController.store.form();
    const slugLocked = (product?.licenses_count ?? 0) > 0;

    return (
        <>
            <Head title={product ? product.name : 'Yeni ürün'} />
            <div className="space-y-10 p-4">
                <section>
                    <Heading title={product ? product.name : 'Yeni ürün'} />
                    {/* İşaretsiz bir checkbox forma hiç eklenmez; transform ile her zaman true/false gönderiyoruz */}
                    <Form {...action} transform={(data) => ({ ...data, is_active: data.is_active === '1' })} options={{ preserveScroll: true }} className="grid max-w-2xl gap-4 sm:grid-cols-2">
                        {({ processing, errors }) => (
                            <>
                                <Field name="name" label="Ürün adı" defaultValue={product?.name} error={errors.name} />
                                <Field
                                    name="slug"
                                    label="Adres (slug)"
                                    defaultValue={product?.slug}
                                    readOnly={slugLocked}
                                    hint={slugLocked ? 'Lisans satıldı: anahtarlarda yazılı olduğu için değiştirilemez.' : 'Lisans anahtarına yazılır; eklenti ürününü bununla tanır.'}
                                    error={errors.slug}
                                />
                                <div className="sm:col-span-2">
                                    <Field name="summary" label="Kısa açıklama" defaultValue={product?.summary} error={errors.summary} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="description">Açıklama</Label>
                                    <textarea id="description" name="description" rows={3} defaultValue={product?.description ?? ''} className="rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                                    <InputError message={errors.description} />
                                </div>
                                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                    <Checkbox name="is_active" value="1" defaultChecked={product?.is_active ?? true} />
                                    Satışta (kapalıysa vitrinde görünmez; satılmış lisanslar çalışmaya devam eder)
                                </label>
                                <div className="sm:col-span-2">
                                    <Button disabled={processing}>{product ? 'Kaydet' : 'Ürünü oluştur'}</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                {product && (
                    <section className="max-w-4xl space-y-4">
                        <Heading title="Planlar" description="Aynı ürünün satış seçenekleri: 1 site, 5 site, sınırsız" />
                        {plans.length === 0 && <p className="text-sm text-amber-700">Bu ürünün planı yok: vitrinde satın alınamaz.</p>}
                        {plans.map((plan) => (
                            <PlanRow key={plan.id} plan={plan} />
                        ))}
                        <div className="rounded-xl border border-dashed p-4">
                            <h3 className="mb-3 text-sm font-medium">Plan ekle</h3>
                            {/* Başarılı eklemeden sonra form boşalsın: resetOnSuccess */}
                            <Form {...PlanController.store.form(product.id)} errorBag="new-plan" resetOnSuccess options={{ preserveScroll: true }} className="grid gap-3 sm:grid-cols-5">
                                {({ processing, errors }) => (
                                    <>
                                        <PlanFields errors={errors} prefix="new-plan" />
                                        <div className="flex items-end">
                                            <Button disabled={processing}>Ekle</Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

ProductForm.layout = { breadcrumbs: [{ title: 'Ürünler', href: ProductController.index() }] };
