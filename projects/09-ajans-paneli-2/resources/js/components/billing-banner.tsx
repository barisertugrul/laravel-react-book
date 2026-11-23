import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import BillingController from '@/actions/App/Http/Controllers/Billing/BillingController';
import { formatDate } from '@/lib/format';

/** Ödeme bekleniyorsa ya da plan sınırı aşıldıysa her sayfanın üstünde görünür */
export default function BillingBanner() {
    const { billing } = usePage().props;

    if (!billing) {
        return null;
    }

    const message = billing.past_due
        ? `Abonelik ödemesi alınamadı. ${billing.grace_ends_at ? `${formatDate(billing.grace_ends_at)} tarihine kadar` : 'Kısa süre'} planınız sürüyor; kartınızı güncelleyin.`
        : billing.sites > billing.max_sites
          ? `${billing.plan} planı ${billing.max_sites} site içeriyor, ajansınızda ${billing.sites} site var. Siteler izlenmeye devam ediyor; toplu işlemler kapalı.`
          : null;

    if (!message) {
        return null;
    }

    return (
        <div className="mx-4 mt-4 flex flex-wrap items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200" data-test="billing-banner">
            <AlertTriangle className="size-4 shrink-0" />
            <span className="flex-1">{message}</span>
            <Link href={BillingController.index()} className="font-medium underline underline-offset-2">
                Abonelik
            </Link>
        </div>
    );
}
