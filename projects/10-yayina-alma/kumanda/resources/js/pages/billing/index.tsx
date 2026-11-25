import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { Check, CreditCard, LoaderCircle } from 'lucide-react';
import BillingController from '@/actions/App/Http/Controllers/Billing/BillingController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Plan, SubscriptionInfo, SubscriptionPayment } from '@/types';

type Props = { plans: Plan[]; current: Plan; sites: number; demo: boolean; subscription: SubscriptionInfo | null; payments: SubscriptionPayment[] };

export default function BillingIndex({ plans, current, sites, demo, subscription, payments }: Props) {
    const page = usePage();
    const { auth } = page.props;
    const isOwner = auth.agency?.role === 'owner';
    const returned = new URLSearchParams(page.url.split('?')[1] ?? '').get('donus');

    // Sağlayıcıdan dönüldü ama abonelik henüz açılmadı: onay webhook'la gelir, birkaç saniye sürebilir
    const waiting = returned === 'tamam' && subscription?.status !== 'active';

    return (
        <>
            <Head title="Abonelik" />
            <div className="space-y-6 p-4">
                <Heading title="Abonelik" description="Plan, site kullanımı ve ödemeler." />

                {/* Canlıda sahte sağlayıcı yalnızca BILLING_ALLOW_FAKE=true ile açılır (demo sunucusu) */}
                {demo && (
                    <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" data-test="billing-demo">
                        Demo: ödemeler sahte sağlayıcıdan geçer, gerçek ödeme alınmaz.
                    </p>
                )}

                {waiting && <WaitForConfirmation />}
                {returned === 'red' && (
                    <p className="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                        Kart reddedildi; abonelik başlatılmadı.
                    </p>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <section className="space-y-3 rounded-xl border p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">{current.name} planı</h2>
                            {subscription && (
                                <Badge variant="secondary" className={cn(subscription.status === 'past_due' && 'bg-amber-100 text-amber-800')}>
                                    {subscription.status_label}
                                </Badge>
                            )}
                        </div>
                        <SiteUsage sites={sites} max={current.max_sites} />
                    </section>

                    {subscription && subscription.status !== 'ended' && (
                        <section className="space-y-3 rounded-xl border p-4 text-sm">
                            <dl className="grid grid-cols-2 gap-3">
                                <div>
                                    <dt className="text-muted-foreground">Kart</dt>
                                    <dd className="flex items-center gap-1.5 font-medium">
                                        <CreditCard className="size-4" /> •••• {subscription.card_last4}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">{subscription.canceled_at ? 'Sona erecek' : 'Sonraki ödeme'}</dt>
                                    <dd className="font-medium">{formatDate(subscription.current_period_ends_at)}</dd>
                                </div>
                            </dl>
                            {subscription.status === 'past_due' && subscription.grace_ends_at && (
                                <p className="text-amber-800 dark:text-amber-300">
                                    Son ödeme alınamadı. Her gün yeniden deniyoruz; {formatDate(subscription.grace_ends_at)} tarihine kadar alınamazsa ajans {plans[0].name} planına geçer.
                                </p>
                            )}
                            {isOwner && (
                                <div className="flex flex-wrap gap-2">
                                    <Form {...BillingController.card.form()}>
                                        {({ processing }) => (
                                            <Button variant="outline" disabled={processing} data-test="update-card-button">
                                                <CreditCard /> Kartı güncelle
                                            </Button>
                                        )}
                                    </Form>
                                    {!subscription.canceled_at && (
                                        <Form {...BillingController.cancel.form()} options={{ preserveScroll: true }}>
                                            {({ processing }) => (
                                                <Button variant="ghost" disabled={processing}>
                                                    Aboneliği iptal et
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            )}
                        </section>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    {plans.map((plan) => {
                        const isCurrent = plan.key === current.key;

                        return (
                            <section key={plan.key} className={cn('flex flex-col gap-3 rounded-xl border p-4', isCurrent && 'border-primary ring-2 ring-primary/15')}>
                                <h3 className="font-medium">{plan.name}</h3>
                                <div className="text-2xl font-semibold tabular-nums">
                                    {plan.price === 0 ? 'Ücretsiz' : formatMoney(plan.price)}
                                    {plan.price > 0 && <span className="text-sm font-normal text-muted-foreground"> / ay</span>}
                                </div>
                                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Check className="size-4" /> {plan.max_sites} siteye kadar
                                </p>
                                {isOwner && (
                                    <Form {...BillingController.subscribe.form(plan.key)} className="mt-auto">
                                        {({ processing }) => (
                                            <Button className="w-full" variant={isCurrent ? 'outline' : 'default'} disabled={isCurrent || processing} data-test={`choose-plan-${plan.key}`}>
                                                {isCurrent ? 'Mevcut plan' : plan.price === 0 ? 'Ücretsiz plana geç' : 'Bu planı seç'}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </section>
                        );
                    })}
                </div>

                <section className="space-y-2">
                    <h2 className="font-medium">Ödemeler</h2>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Tarih</th>
                                    <th className="p-3 font-medium">Plan</th>
                                    <th className="p-3 text-right font-medium">Tutar</th>
                                    <th className="p-3 font-medium">Sonuç</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {payments.map((payment) => (
                                    <tr key={payment.id}>
                                        <td className="p-3 whitespace-nowrap tabular-nums">{formatDateTime(payment.created_at)}</td>
                                        <td className="p-3">{plans.find((p) => p.key === payment.plan)?.name ?? payment.plan}</td>
                                        <td className="p-3 text-right tabular-nums">{formatMoney(payment.amount)}</td>
                                        <td className={cn('p-3', payment.status === 'failed' && 'text-red-700 dark:text-red-400')}>
                                            {payment.status === 'paid' ? `Ödendi · ${payment.reference}` : payment.failure_message}
                                        </td>
                                    </tr>
                                ))}
                                {payments.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                            Henüz ödeme yok.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

function SiteUsage({ sites, max }: { sites: number; max: number }) {
    const percent = Math.min(100, Math.round((sites / max) * 100));

    return (
        <div className="space-y-1.5 text-sm">
            <div className="flex justify-between">
                <span className="text-muted-foreground">Siteler</span>
                <span className={cn('font-medium tabular-nums', sites > max && 'text-amber-700 dark:text-amber-400')}>
                    {sites} / {max}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div className={cn('h-full rounded-full', sites > max ? 'bg-amber-500' : 'bg-primary')} style={{ width: `${percent}%` }} />
            </div>
        </div>
    );
}

/** Abonelik webhook'la açılır: gelene kadar sayfa kendini yeniler */
function WaitForConfirmation() {
    usePoll(2_000, { only: ['subscription', 'current', 'payments', 'billing'] });

    return (
        <p className="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm text-muted-foreground" data-test="waiting-confirmation">
            <LoaderCircle className="size-4 animate-spin" /> Ödeme sağlayıcısının onayı bekleniyor…
        </p>
    );
}

BillingIndex.layout = { breadcrumbs: [{ title: 'Abonelik', href: BillingController.index() }] };
