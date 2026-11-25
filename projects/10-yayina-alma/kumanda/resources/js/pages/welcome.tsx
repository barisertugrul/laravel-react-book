import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, Check, PackagePlus, ShieldCheck, Users } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { dashboard, login, register } from '@/routes';

type Plan = { key: string; name: string; price: number; max_sites: number };

// Başlangıç kitinin İngilizce örnek sayfası (Laravel'in belgelerine ve barındırma hizmetine bağlantılar)
// yayına alınmadan önce ürünün kendi tanıtım sayfasıyla değiştirildi (Bölüm 10).
const features = [
    { icon: Activity, title: 'Sağlık izleme', text: 'Sitelerin erişilebilirliği, yanıt süresi ve SSL sertifikası düzenli olarak denetlenir; sorun olunca hemen görürsünüz.' },
    { icon: PackagePlus, title: 'Toplu işlemler', text: 'Eklenti ve temaları onlarca siteye tek seferde kurun ya da güncelleyin; ilerlemeyi canlı izleyin.' },
    { icon: ShieldCheck, title: 'İmzalı bağlantı', text: 'Siteleriniz Ajans Asistanı eklentisiyle, her iki yönde imzalı isteklerle bağlanır; şifre paylaşmazsınız.' },
    { icon: Users, title: 'Ekip', text: 'Ajansınıza ekip arkadaşlarınızı davet edin; her ajansın verisi yalnızca kendisine görünür.' },
];

export default function Welcome({ plans }: { plans: Plan[] }) {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="WordPress ajansları için yönetim paneli" />
            <div className="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
                <header className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                    <div className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-7 fill-current" />
                        {name}
                    </div>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Panele git</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={login()}>Giriş yap</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>Ücretsiz başla</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto max-w-5xl px-6 pb-16">
                    <section className="py-12">
                        <h1 className="max-w-2xl text-4xl font-semibold tracking-tight">WordPress sitelerinizi tek panelden yönetin</h1>
                        <p className="mt-4 max-w-2xl text-lg text-neutral-600 dark:text-neutral-400">
                            {name}, müşterilerinin sitelerini yöneten ajanslar için: sağlık izleme, toplu eklenti kurulumu ve ekip yönetimi bir arada.
                        </p>
                    </section>

                    <section className="grid gap-4 sm:grid-cols-2">
                        {features.map(({ icon: Icon, title, text }) => (
                            <div key={title} className="rounded-xl border bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                                <Icon className="size-5 text-indigo-600 dark:text-indigo-400" />
                                <h2 className="mt-3 font-medium">{title}</h2>
                                <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{text}</p>
                            </div>
                        ))}
                    </section>

                    <section className="mt-14">
                        <h2 className="text-2xl font-semibold">Planlar</h2>
                        <div data-test="plans" className="mt-5 grid gap-4 md:grid-cols-3">
                            {plans.map((plan) => (
                                <div key={plan.key} className="rounded-xl border bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                                    <h3 className="font-medium">{plan.name}</h3>
                                    <div className="mt-2 text-3xl font-semibold tabular-nums">{plan.price === 0 ? 'Ücretsiz' : formatMoney(plan.price)}</div>
                                    {plan.price > 0 && <div className="text-sm text-neutral-500">aylık</div>}
                                    <p className="mt-4 flex items-center gap-2 text-sm">
                                        <Check className="size-4 text-indigo-600" /> {plan.max_sites} siteye kadar
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
