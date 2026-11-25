import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, Infinity as InfinityIcon, KeyRound, RefreshCw, Shield } from 'lucide-react';
import PurchaseController from '@/actions/App/Http/Controllers/PurchaseController';
import { Button } from '@/components/ui/button';
import { formatSites } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { login } from '@/routes';
import type { Plan, Product } from '@/types';

function PlanCard({ plan, featured }: { plan: Plan; featured: boolean }) {
    const { auth } = usePage().props;

    return (
        <div className={`flex flex-col rounded-xl border bg-white p-5 dark:bg-neutral-900 ${featured ? 'border-indigo-500 ring-1 ring-indigo-500' : ''}`}>
            <div className="flex items-center justify-between">
                <h3 className="font-medium">{plan.name}</h3>
                {featured && <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-200">En çok tercih edilen</span>}
            </div>
            <div className="mt-3 text-3xl font-semibold tabular-nums">{formatMoney(plan.price)}</div>
            <div className="text-sm text-muted-foreground">tek seferlik ödeme</div>
            <ul className="mt-4 flex-1 space-y-2 text-sm">
                <li className="flex gap-2">
                    {plan.max_sites === null ? <InfinityIcon className="size-4 text-indigo-600" /> : <Check className="size-4 text-indigo-600" />}
                    {formatSites(plan.max_sites)}
                </li>
                <li className="flex gap-2">
                    <Check className="size-4 text-indigo-600" /> Ömür boyu kullanım
                </li>
                <li className="flex gap-2">
                    <Check className="size-4 text-indigo-600" /> 1 yıl güncelleme ve destek
                </li>
            </ul>
            {auth.user ? (
                // Satın al: sipariş oluşturulur ve ödeme sağlayıcısına gidilir (Inertia::location)
                <Form {...PurchaseController.store.form(plan.id)} className="mt-5">
                    {({ processing }) => (
                        <Button className="w-full" variant={featured ? 'default' : 'outline'} disabled={processing}>
                            Satın al
                        </Button>
                    )}
                </Form>
            ) : (
                <Button asChild className="mt-5 w-full" variant={featured ? 'default' : 'outline'}>
                    <Link href={login()}>Satın almak için giriş yapın</Link>
                </Button>
            )}
        </div>
    );
}

export default function Store({ products }: { products: Product[] }) {
    return (
        <>
            <Head title="WordPress eklentileri" />
            <section className="mb-10 rounded-2xl bg-indigo-950 px-6 py-10 text-white">
                <h1 className="text-3xl font-semibold">Ajanslar için WordPress eklentileri</h1>
                <p className="mt-2 max-w-2xl text-indigo-200">
                    Bir kez satın alın, sonsuza kadar kullanın. Lisans anahtarınız ödemeden hemen sonra e-postanıza ve hesabınıza gelir.
                </p>
                <div className="mt-6 flex flex-wrap gap-6 text-sm text-indigo-100">
                    <span className="flex items-center gap-2"><KeyRound className="size-4" /> Anında lisans</span>
                    <span className="flex items-center gap-2"><RefreshCw className="size-4" /> 1 yıl güncelleme</span>
                    <span className="flex items-center gap-2"><Shield className="size-4" /> Siteleri kendiniz yönetin</span>
                </div>
            </section>

            <div className="space-y-12">
                {products.map((product) => (
                    <section key={product.id} id={product.slug}>
                        <h2 className="text-2xl font-semibold">{product.name}</h2>
                        <p className="mt-1 text-muted-foreground">{product.summary}</p>
                        {product.description && <p className="mt-3 max-w-3xl text-sm">{product.description}</p>}
                        <div className="mt-5 grid gap-4 md:grid-cols-3">
                            {product.plans?.map((plan, i) => <PlanCard key={plan.id} plan={plan} featured={i === 1} />)}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}
