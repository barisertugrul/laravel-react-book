import { formatMonth } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import type { MonthlyTotal } from '@/types';

/**
 * Aylık satış/alış sütun grafiği. Kütüphane kullanmadan, düz HTML ve Tailwind ile:
 * her sütunun yüksekliği, en büyük değere oranlanmış bir yüzdedir.
 */
export default function MonthlyChart({ data }: { data: MonthlyTotal[] }) {
    const max = Math.max(1, ...data.flatMap((m) => [m.sales, m.purchase]));

    return (
        <div>
            <div className="flex h-48 items-end gap-3">
                {data.map((m) => (
                    <div
                        key={m.month}
                        className="flex h-full flex-1 flex-col justify-end"
                    >
                        <div className="flex h-full items-end justify-center gap-1">
                            <div
                                className="w-1/2 max-w-6 rounded-t bg-emerald-500"
                                style={{ height: `${(m.sales / max) * 100}%` }}
                                title={`Satış: ${formatMoney(m.sales)}`}
                            />
                            <div
                                className="w-1/2 max-w-6 rounded-t bg-slate-400"
                                style={{
                                    height: `${(m.purchase / max) * 100}%`,
                                }}
                                title={`Alış: ${formatMoney(m.purchase)}`}
                            />
                        </div>
                    </div>
                ))}
            </div>
            <div className="mt-2 flex gap-3 border-t pt-2">
                {data.map((m) => (
                    <div
                        key={m.month}
                        className="flex-1 text-center text-xs text-muted-foreground"
                    >
                        {formatMonth(m.month)}
                    </div>
                ))}
            </div>
            <div className="mt-3 flex gap-4 text-xs">
                <span className="flex items-center gap-1">
                    <span className="size-3 rounded-sm bg-emerald-500" /> Satış
                </span>
                <span className="flex items-center gap-1">
                    <span className="size-3 rounded-sm bg-slate-400" /> Alış
                </span>
            </div>
        </div>
    );
}
