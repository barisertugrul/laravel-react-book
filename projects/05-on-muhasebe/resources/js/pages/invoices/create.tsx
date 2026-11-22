import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import FormField, { selectClass } from '@/components/form-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { calculateLine, calculateTotals, formatMoney } from '@/lib/money';
import type { LineInput } from '@/lib/money';
import type { InvoiceType } from '@/types';

type Line = LineInput & { key: number; description: string };

type Props = {
    type: InvoiceType;
    typeLabel: string;
    contacts: { id: number; name: string }[];
    defaults: {
        issue_date: string;
        due_date: string;
        contact_id: number | null;
    };
};

const VAT_RATES = ['0', '1', '10', '20'];

// React listelerinde her satırın kalıcı bir anahtarı olmalı; dizi sırası (index) yeterli değil,
// çünkü ortadan bir satır silindiğinde alttaki satırların girdileri karışır.
let nextKey = 1;
const emptyLine = (): Line => ({
    key: nextKey++,
    description: '',
    quantity: '1',
    unit_price: '',
    discount_rate: '0',
    vat_rate: '20',
});

export default function InvoicesCreate({
    type,
    typeLabel,
    contacts,
    defaults,
}: Props) {
    const form = useForm({
        type,
        contact_id: defaults.contact_id ? String(defaults.contact_id) : '',
        issue_date: defaults.issue_date,
        due_date: defaults.due_date,
        notes: '',
        lines: [emptyLine()],
    });

    // Canlı toplamlar: sunucudaki hesapla aynı kurallar (lib/money.ts)
    const totals = calculateTotals(form.data.lines);
    // useForm, iç içe alanların hatalarını "lines.0.quantity" gibi düz anahtarlarla verir
    const errors = form.errors as Record<string, string | undefined>;

    function updateLine(
        index: number,
        field: keyof LineInput | 'description',
        value: string,
    ) {
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i === index ? { ...line, [field]: value } : line,
            ),
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        // "key" yalnızca React için; sunucuya göndermeyelim
        form.transform((data) => ({
            ...data,
            lines: data.lines.map(({ key: _key, ...line }) => line),
        }));
        form.submit(InvoiceController.store());
    }

    return (
        <>
            <Head title={`Yeni ${typeLabel.toLowerCase()} faturası`} />
            <form onSubmit={submit} className="space-y-6 p-4">
                <Heading
                    title={`Yeni ${typeLabel.toLowerCase()} faturası`}
                    description="Numara kayıt sırasında otomatik verilir."
                />

                <div className="grid max-w-4xl gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="contact_id">
                            {type === 'sales' ? 'Müşteri' : 'Tedarikçi'}
                        </Label>
                        <select
                            id="contact_id"
                            value={form.data.contact_id}
                            onChange={(e) =>
                                form.setData('contact_id', e.target.value)
                            }
                            className={selectClass}
                        >
                            <option value="">Seçin…</option>
                            {contacts.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.contact_id} />
                    </div>
                    <FormField
                        name="issue_date"
                        label="Fatura tarihi"
                        type="date"
                        value={form.data.issue_date}
                        onChange={(e) =>
                            form.setData('issue_date', e.target.value)
                        }
                        error={form.errors.issue_date}
                    />
                    <FormField
                        name="due_date"
                        label="Vade"
                        type="date"
                        value={form.data.due_date}
                        onChange={(e) =>
                            form.setData('due_date', e.target.value)
                        }
                        error={form.errors.due_date}
                    />
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-2 font-medium">Açıklama</th>
                                <th className="w-24 p-2 font-medium">Miktar</th>
                                <th className="w-32 p-2 font-medium">
                                    Birim fiyat
                                </th>
                                <th className="w-20 p-2 font-medium">İsk. %</th>
                                <th className="w-24 p-2 font-medium">KDV</th>
                                <th className="w-32 p-2 text-right font-medium">
                                    Tutar
                                </th>
                                <th className="w-10 p-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {form.data.lines.map((line, i) => {
                                const calc = calculateLine(line);
                                return (
                                    <tr key={line.key} className="align-top">
                                        <td className="p-2">
                                            <Input
                                                value={line.description}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Ürün veya hizmet"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${i}.description`
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="p-2">
                                            <Input
                                                value={line.quantity}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'quantity',
                                                        e.target.value,
                                                    )
                                                }
                                                inputMode="decimal"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${i}.quantity`
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="p-2">
                                            <Input
                                                value={line.unit_price}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'unit_price',
                                                        e.target.value,
                                                    )
                                                }
                                                inputMode="decimal"
                                                placeholder="0,00"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${i}.unit_price`
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="p-2">
                                            <Input
                                                value={line.discount_rate}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'discount_rate',
                                                        e.target.value,
                                                    )
                                                }
                                                inputMode="numeric"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${i}.discount_rate`
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="p-2">
                                            <select
                                                value={line.vat_rate}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'vat_rate',
                                                        e.target.value,
                                                    )
                                                }
                                                className={selectClass}
                                            >
                                                {VAT_RATES.map((r) => (
                                                    <option key={r} value={r}>
                                                        %{r}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="p-2 pt-4 text-right tabular-nums">
                                            {calc ? formatMoney(calc.net) : '—'}
                                        </td>
                                        <td className="p-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    form.data.lines.length === 1
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.filter(
                                                            (_, j) => j !== i,
                                                        ),
                                                    )
                                                }
                                                aria-label="Satırı sil"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <InputError message={form.errors.lines} />

                <div className="flex flex-wrap items-start justify-between gap-6">
                    <div className="space-y-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData('lines', [
                                    ...form.data.lines,
                                    emptyLine(),
                                ])
                            }
                            disabled={form.data.lines.length >= 50}
                        >
                            <Plus /> Satır ekle
                        </Button>
                        <div className="grid w-80 gap-2">
                            <Label htmlFor="notes">
                                Not (faturada görünür)
                            </Label>
                            <textarea
                                id="notes"
                                rows={2}
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                                className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <dl className="w-72 space-y-1 text-sm">
                        <Row
                            label="Ara toplam"
                            value={formatMoney(totals.subtotal)}
                        />
                        {totals.discount_total > 0 && (
                            <Row
                                label="İskonto"
                                value={`-${formatMoney(totals.discount_total)}`}
                            />
                        )}
                        {Object.entries(totals.vat_breakdown).map(
                            ([rate, vat]) => (
                                <Row
                                    key={rate}
                                    label={`KDV (%${rate})`}
                                    value={formatMoney(vat)}
                                />
                            ),
                        )}
                        <div className="flex justify-between border-t pt-2 text-base font-semibold">
                            <dt>Genel toplam</dt>
                            <dd className="tabular-nums">
                                {formatMoney(totals.total)}
                            </dd>
                        </div>
                        <Button
                            className="mt-3 w-full"
                            disabled={form.processing}
                        >
                            Faturayı kaydet
                        </Button>
                    </dl>
                </div>
            </form>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="tabular-nums">{value}</dd>
        </div>
    );
}

InvoicesCreate.layout = {
    breadcrumbs: [
        { title: 'Faturalar', href: InvoiceController.index() },
        { title: 'Yeni fatura', href: InvoiceController.create() },
    ],
};
