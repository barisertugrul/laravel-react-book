import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Clock, Scissors } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import BookingController from '@/actions/App/Http/Controllers/BookingController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDay, formatPrice } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import type { BookingDay, Service } from '@/types';

type Props = {
    business: string;
    services: Service[];
    days: BookingDay[];
    selected: { service: number | null; date: string | null };
    slots?: string[]; // optional prop: yalnızca istendiğinde gelir
};

export default function BookingShow({ business, services, days, selected, slots }: Props) {
    const form = useForm({
        service_id: selected.service,
        date: selected.date,
        time: '',
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        note: '',
    });
    const { data, setData, errors, processing } = form;

    /**
     * Hizmet veya gün değiştiğinde yalnızca "slots" prop'unu sunucudan yeniden ister.
     * Sayfanın geri kalanı (hizmetler, günler) tekrar gönderilmez: kısmi yenileme.
     */
    function choose(changes: { service_id?: number; date?: string }) {
        const next = { ...data, ...changes, time: '' };
        setData(next);

        if (next.service_id && next.date) {
            router.reload({
                only: ['slots'],
                data: { service: next.service_id, date: next.date },
            });
        }
    }

    // Saatler henüz yüklenmediyse (paylaşılan bağlantı ya da hata sonrası dönüş) iste.
    useEffect(() => {
        if (slots === undefined && data.service_id && data.date) {
            router.reload({ only: ['slots'], data: { service: data.service_id, date: data.date } });
        }
    }, [slots, data.service_id, data.date]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(BookingController.store.url());
    }

    const step = !data.service_id ? 1 : !data.date ? 2 : !data.time ? 3 : 4;

    return (
        <>
            <Head title="Randevu al" />
            <div className="min-h-screen bg-stone-50 text-stone-900">
                <header className="bg-stone-900 px-4 py-6 text-center text-white">
                    <Scissors className="mx-auto mb-2 size-6 text-amber-400" />
                    <h1 className="text-xl font-semibold">{business}</h1>
                    <p className="text-sm text-stone-400">Online randevu</p>
                </header>

                <form onSubmit={submit} className="mx-auto max-w-xl space-y-8 px-4 py-6">
                    {/* 1. Hizmet */}
                    <section>
                        <h2 className="mb-3 font-semibold">1. Hizmet seçin</h2>
                        <div className="grid gap-2">
                            {services.map((service) => (
                                <button
                                    key={service.id}
                                    type="button"
                                    onClick={() => choose({ service_id: service.id })}
                                    className={cn(
                                        'flex items-center justify-between rounded-xl border bg-white p-4 text-left transition',
                                        data.service_id === service.id ? 'border-stone-900 ring-2 ring-stone-900' : 'hover:border-stone-400',
                                    )}
                                >
                                    <span>
                                        <span className="block font-medium">{service.name}</span>
                                        <span className="flex items-center gap-1 text-sm text-stone-500">
                                            <Clock className="size-3.5" /> {service.duration} dk
                                        </span>
                                    </span>
                                    <span className="font-semibold">{formatPrice(service.price)}</span>
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.service_id} />
                    </section>

                    {/* 2. Gün */}
                    {step >= 2 && (
                        <section>
                            <h2 className="mb-3 font-semibold">2. Gün seçin</h2>
                            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2">
                                {days.map((day) => (
                                    <button
                                        key={day.date}
                                        type="button"
                                        disabled={!day.open}
                                        onClick={() => choose({ date: day.date })}
                                        className={cn(
                                            'flex w-16 shrink-0 flex-col items-center rounded-xl border bg-white py-2 text-sm transition',
                                            data.date === day.date && 'border-stone-900 bg-stone-900 text-white',
                                            !day.open && 'cursor-not-allowed opacity-40',
                                        )}
                                    >
                                        <span className="text-xs">{formatDay(day.date, { weekday: 'short' })}</span>
                                        <span className="text-lg font-semibold">{formatDay(day.date, { day: 'numeric' })}</span>
                                        <span className="text-xs">{formatDay(day.date, { month: 'short' })}</span>
                                    </button>
                                ))}
                            </div>
                            <InputError message={errors.date} />
                        </section>
                    )}

                    {/* 3. Saat */}
                    {step >= 3 && (
                        <section>
                            <h2 className="mb-3 flex items-center gap-2 font-semibold">
                                3. Saat seçin
                                {slots === undefined && <Spinner />}
                            </h2>
                            {slots && slots.length === 0 && (
                                <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                                    Bu gün için boş saat kalmadı. Lütfen başka bir gün seçin.
                                </p>
                            )}
                            <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                {slots?.map((time) => (
                                    <button
                                        key={time}
                                        type="button"
                                        onClick={() => setData('time', time)}
                                        className={cn(
                                            'rounded-lg border bg-white py-2 text-sm font-medium tabular-nums transition',
                                            data.time === time ? 'border-stone-900 bg-stone-900 text-white' : 'hover:border-stone-400',
                                        )}
                                    >
                                        {time}
                                    </button>
                                ))}
                            </div>
                            <InputError message={errors.time} />
                        </section>
                    )}

                    {/* 4. Bilgiler */}
                    {step >= 4 && (
                        <section className="space-y-4 rounded-xl border bg-white p-4">
                            <h2 className="flex items-center gap-2 font-semibold">
                                <CalendarDays className="size-4" />
                                {data.date && formatDay(data.date, { day: 'numeric', month: 'long', weekday: 'long' })}, {data.time}
                            </h2>
                            <div className="grid gap-2">
                                <Label htmlFor="customer_name">Ad soyad</Label>
                                <Input id="customer_name" value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} autoComplete="name" />
                                <InputError message={errors.customer_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="customer_phone">Telefon</Label>
                                <Input id="customer_phone" type="tel" value={data.customer_phone} onChange={(e) => setData('customer_phone', e.target.value)} autoComplete="tel" />
                                <InputError message={errors.customer_phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="customer_email">E-posta (onay ve hatırlatma için)</Label>
                                <Input id="customer_email" type="email" value={data.customer_email} onChange={(e) => setData('customer_email', e.target.value)} autoComplete="email" />
                                <InputError message={errors.customer_email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="note">Not (isteğe bağlı)</Label>
                                <Input id="note" value={data.note} onChange={(e) => setData('note', e.target.value)} />
                            </div>
                            <Button className="w-full" size="lg" disabled={processing}>
                                {processing && <Spinner />} Randevuyu onayla
                            </Button>
                        </section>
                    )}
                </form>
            </div>
        </>
    );
}
