import { Head } from '@inertiajs/react';
import { CreditCard, ShieldCheck } from 'lucide-react';
import { formatMoney } from '@/lib/money';

type Props = { order: { number: string; total: number }; merchant: string; action: string; csrf: string };

/**
 * SAHTE ödeme sağlayıcısının sayfası. Gerçekte bu sayfa sağlayıcının sitesinde olurdu;
 * kart bilgisi mağazanın sunucusuna hiç gelmezdi. Bu yüzden burada kart alanı yok.
 *
 * Düz bir HTML formu kullanıyoruz (Inertia değil): sağlayıcının sitesinden mağazaya
 * dönüş, tam sayfa bir yönlendirmedir.
 */
export default function FakeProvider({ order, merchant, action, csrf }: Props) {

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
                    <div className="mt-6 text-sm text-slate-500">{merchant} · {order.number}</div>
                    <div className="text-3xl font-semibold tabular-nums">{formatMoney(order.total)}</div>

                    <div className="mt-6 flex items-center gap-3 rounded-lg border border-dashed p-3 text-sm text-slate-500">
                        <CreditCard className="shrink-0" />
                        Gerçek bir sağlayıcıda kart bilgileri bu sayfada girilir. Test için sonucu seçin:
                    </div>

                    <form method="post" action={action} className="mt-6 grid gap-2">
                        <input type="hidden" name="_token" value={csrf} />
                        <button name="result" value="approve" className="rounded-lg bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-700">
                            Ödemeyi onayla
                        </button>
                        <button name="result" value="decline" className="rounded-lg border px-4 py-3 font-medium hover:bg-slate-50">
                            Kart reddedildi
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
