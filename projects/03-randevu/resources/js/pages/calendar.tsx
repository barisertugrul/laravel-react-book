import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Mail, Phone } from 'lucide-react';
import { useState } from 'react';
import AppointmentStatusController from '@/actions/App/Http/Controllers/Admin/AppointmentStatusController';
import CalendarController from '@/actions/App/Http/Controllers/Admin/CalendarController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { addDays, formatDay, formatLongDate, formatTime, localParts } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import type { Appointment, AppointmentStatus } from '@/types';

type Props = {
    weekStart: string;
    appointments: Appointment[];
    range: { from: number; to: number };
    closedDays: number[];
};

/** Takvimde 1 saatin yüksekliği (piksel). Randevu blokları buna göre konumlanır. */
const HOUR_HEIGHT = 64;

const statusStyles: Record<AppointmentStatus, string> = {
    confirmed: 'border-blue-500 bg-blue-50 text-blue-950 dark:bg-blue-950 dark:text-blue-50',
    completed: 'border-green-600 bg-green-50 text-green-950 dark:bg-green-950 dark:text-green-50',
    cancelled: 'border-neutral-300 bg-neutral-100 text-neutral-400 line-through dark:bg-neutral-800',
};

const statusLabels: Record<AppointmentStatus, string> = {
    confirmed: 'Onaylandı',
    completed: 'Tamamlandı',
    cancelled: 'İptal edildi',
};

export default function Calendar({ weekStart, appointments, range, closedDays }: Props) {
    const [selected, setSelected] = useState<Appointment | null>(null);
    const days = Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));
    const hours = Array.from({ length: range.to - range.from }, (_, i) => range.from + i);
    const today = localParts(new Date().toISOString()).date;

    const goToWeek = (offset: number) =>
        router.get(CalendarController.url({ query: { week: addDays(weekStart, offset * 7) } }), {}, { preserveScroll: true });

    function setStatus(appointment: Appointment, status: AppointmentStatus) {
        router.patch(AppointmentStatusController.url(appointment.id), { status }, {
            preserveScroll: true,
            onSuccess: () => setSelected(null),
        });
    }

    return (
        <>
            <Head title="Takvim" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <Button size="icon" variant="outline" onClick={() => goToWeek(-1)} aria-label="Önceki hafta">
                        <ChevronLeft />
                    </Button>
                    <Button size="icon" variant="outline" onClick={() => goToWeek(1)} aria-label="Sonraki hafta">
                        <ChevronRight />
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={CalendarController()}>Bugün</Link>
                    </Button>
                    <h2 className="ml-2 text-lg font-semibold">
                        {formatDay(days[0], { day: 'numeric', month: 'long' })} – {formatDay(days[6], { day: 'numeric', month: 'long', year: 'numeric' })}
                    </h2>
                    <span className="ml-auto text-sm text-muted-foreground">
                        {appointments.filter((a) => a.status !== 'cancelled').length} randevu
                    </span>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <div className="grid min-w-[900px]" style={{ gridTemplateColumns: '56px repeat(7, 1fr)' }}>
                        {/* Başlık satırı */}
                        <div className="border-b" />
                        {days.map((day) => (
                            <div key={day} className={cn('border-b border-l p-2 text-center text-sm', day === today && 'bg-primary/5 font-semibold')}>
                                {formatDay(day)}
                            </div>
                        ))}

                        {/* Saat etiketleri */}
                        <div className="relative">
                            {hours.map((hour) => (
                                <div key={hour} className="pr-2 text-right text-xs text-muted-foreground" style={{ height: HOUR_HEIGHT }}>
                                    {String(hour).padStart(2, '0')}:00
                                </div>
                            ))}
                        </div>

                        {/* Gün sütunları */}
                        {days.map((day, i) => {
                            const closed = closedDays.includes(i + 1);
                            const dayAppointments = appointments.filter((a) => localParts(a.starts_at).date === day);

                            return (
                                <div
                                    key={day}
                                    className={cn('relative border-l', closed && 'bg-[repeating-linear-gradient(45deg,transparent,transparent_6px,rgba(0,0,0,0.04)_6px,rgba(0,0,0,0.04)_12px)]')}
                                    style={{ height: hours.length * HOUR_HEIGHT }}
                                >
                                    {hours.map((hour) => (
                                        <div key={hour} className="border-b border-dashed" style={{ height: HOUR_HEIGHT }} />
                                    ))}

                                    {dayAppointments.map((appointment) => {
                                        // Konum = başlangıç dakikası, yükseklik = süre. Takvimin tüm hilesi bu iki satır.
                                        const start = localParts(appointment.starts_at).minutes - range.from * 60;
                                        const end = localParts(appointment.ends_at).minutes - range.from * 60;

                                        return (
                                            <button
                                                key={appointment.id}
                                                type="button"
                                                onClick={() => setSelected(appointment)}
                                                className={cn('absolute inset-x-1 overflow-hidden rounded-md border-l-4 px-1.5 py-0.5 text-left text-xs shadow-sm', statusStyles[appointment.status])}
                                                style={{ top: (start / 60) * HOUR_HEIGHT, height: ((end - start) / 60) * HOUR_HEIGHT - 2 }}
                                            >
                                                <span className="font-semibold tabular-nums">{formatTime(appointment.starts_at)}</span>{' '}
                                                {appointment.customer_name}
                                                <span className="block truncate opacity-75">{appointment.service?.name}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            <Dialog open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                {selected && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{selected.customer_name}</DialogTitle>
                            <DialogDescription>
                                {selected.service?.name} · {formatLongDate(selected.starts_at)}, {formatTime(selected.starts_at)}–{formatTime(selected.ends_at)}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2 text-sm">
                            <Badge variant="secondary">{statusLabels[selected.status]}</Badge>
                            <p className="flex items-center gap-2"><Phone className="size-4" /> <a href={`tel:${selected.customer_phone}`}>{selected.customer_phone}</a></p>
                            <p className="flex items-center gap-2"><Mail className="size-4" /> {selected.customer_email}</p>
                            {selected.note && <p className="rounded bg-muted p-2">{selected.note}</p>}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {selected.status !== 'completed' && (
                                <Button onClick={() => setStatus(selected, 'completed')}>Tamamlandı</Button>
                            )}
                            {selected.status !== 'cancelled' && (
                                <Button variant="destructive" onClick={() => setStatus(selected, 'cancelled')}>İptal et</Button>
                            )}
                            {selected.status !== 'confirmed' && (
                                <Button variant="outline" onClick={() => setStatus(selected, 'confirmed')}>Onaylandı yap</Button>
                            )}
                        </div>
                    </DialogContent>
                )}
            </Dialog>
        </>
    );
}

Calendar.layout = {
    breadcrumbs: [{ title: 'Takvim', href: CalendarController() }],
};
