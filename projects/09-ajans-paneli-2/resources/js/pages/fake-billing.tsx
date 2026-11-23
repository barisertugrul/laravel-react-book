import { Head } from '@inertiajs/react';
import { CreditCard, ShieldCheck } from 'lucide-react';
import { formatMoney } from '@/lib/money';
import type { Plan } from '@/types';

type Props = { merchant: string; agency: string; plan: Plan; purpose: 'subscribe' | 'card'; cards: string[]; action: string; csrf: string };

const cardNotes: Record<string, string> = {
    '4242': 'Her ödeme başarılı',
    '0341': 'İlk ödeme geçer, yenilemeler reddedilir',
    '0002': 'Her ödeme reddedilir',
};

/**
 * SAHTE abonelik sağlayıcısının sayfası (Bölüm 6'daki fake-provider'ın kardeşi).
 * Gerçekte kart bu sayfada, sağlayıcının sitesinde girilir ve sağlayıcıda saklanır;
 * Kumanda'ya yalnızca kartın jetonu ve son 4 hanesi döner.
 */
export default function FakeBilling({ merchant, agency, plan, purpose, cards, action, csrf }: Props) {
    return (
        <>
            <Head title="Sahte Ödeme A.Ş." />
            <div className="flex min-h-screen items-center justify-center bg-indigo-950 p-4 text-white">
                <div className="w-full max-w-sm rounded-2xl bg-white p-6 text-slate-900 shadow-xl">
                    <div className="flex items-center gap-2 text-indigo-700">
                        <ShieldCheck />
                        <span className="font-semibold">Sahte Ödeme A.Ş.</span>
                        <span className="ml-auto rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">TEST</span>
                    </div>
                    <div className="mt-6 text-sm text-slate-500">
                        {merchant} · {agency}
                    </div>
                    {purpose === 'subscribe' ? (
                        <div className="text-3xl font-semibold tabular-nums">
                            {formatMoney(plan.price)} <span className="text-base font-normal text-slate-500">/ ay · {plan.name}</span>
                        </div>
                    ) : (
                        <div className="text-xl font-semibold">Kayıtlı kartı değiştir</div>
                    )}

                    <div className="mt-6 flex items-center gap-3 rounded-lg border border-dashed p-3 text-sm text-slate-500">
                        <CreditCard className="shrink-0" />
                        Kart sağlayıcıda saklanır ve her ay otomatik çekilir. Test kartını seçin:
                    </div>

                    <form method="post" action={action} className="mt-6 grid gap-2">
                        <input type="hidden" name="_token" value={csrf} />
                        {cards.map((card, i) => (
                            <button
                                key={card}
                                name="card"
                                value={card}
                                data-test={`card-${card}`}
                                className={i === 0 ? 'rounded-lg bg-indigo-600 px-4 py-3 text-left font-medium text-white hover:bg-indigo-700' : 'rounded-lg border px-4 py-3 text-left font-medium hover:bg-slate-50'}
                            >
                                <span className="font-mono">•••• {card}</span>
                                <span className={i === 0 ? 'block text-xs text-indigo-100' : 'block text-xs text-slate-500'}>{cardNotes[card]}</span>
                            </button>
                        ))}
                    </form>
                </div>
            </div>
        </>
    );
}
