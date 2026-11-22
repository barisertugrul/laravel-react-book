import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import WorkingHoursController from '@/actions/App/Http/Controllers/Admin/WorkingHoursController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Day = { weekday: number; name: string; is_open: boolean; opens_at: string; closes_at: string };

export default function WorkingHours({ hours }: { hours: Day[] }) {
    const form = useForm({ hours });
    const errors = form.errors as Record<string, string | undefined>;

    const update = (index: number, changes: Partial<Day>) =>
        form.setData('hours', form.data.hours.map((day, i) => (i === index ? { ...day, ...changes } : day)));

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(WorkingHoursController.update(), { preserveScroll: true });
    }

    return (
        <>
            <Head title="Çalışma saatleri" />
            <form onSubmit={submit} className="max-w-xl space-y-4 p-4">
                <Heading title="Çalışma saatleri" description="Müşteriler yalnızca açık olduğunuz saatlerde randevu alabilir." />
                <div className="divide-y rounded-xl border">
                    {form.data.hours.map((day, i) => (
                        <div key={day.weekday} className="p-3">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id={`open-${day.weekday}`}
                                    checked={day.is_open}
                                    onCheckedChange={(checked) => update(i, { is_open: checked === true })}
                                />
                                <label htmlFor={`open-${day.weekday}`} className="w-24 font-medium">{day.name}</label>
                                <div className={cn('flex items-center gap-2', !day.is_open && 'pointer-events-none opacity-40')}>
                                    <Input type="time" step={900} className="w-28" value={day.opens_at} onChange={(e) => update(i, { opens_at: e.target.value })} aria-label={`${day.name} açılış`} />
                                    <span>–</span>
                                    <Input type="time" step={900} className="w-28" value={day.closes_at} onChange={(e) => update(i, { closes_at: e.target.value })} aria-label={`${day.name} kapanış`} />
                                </div>
                                {!day.is_open && <span className="text-sm text-muted-foreground">Kapalı</span>}
                            </div>
                            <InputError message={errors[`hours.${i}.opens_at`] ?? errors[`hours.${i}.closes_at`]} className="mt-1 ml-9" />
                        </div>
                    ))}
                </div>
                <Button disabled={form.processing}>Kaydet</Button>
            </form>
        </>
    );
}

WorkingHours.layout = {
    breadcrumbs: [{ title: 'Çalışma saatleri', href: WorkingHoursController.edit() }],
};
