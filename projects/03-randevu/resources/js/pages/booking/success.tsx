import { Head, Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/BookingController';
import { formatLongDate, formatTime } from '@/lib/datetime';
import type { Appointment } from '@/types';

export default function BookingSuccess({ appointment, cancelUrl }: { appointment: Appointment; cancelUrl: string }) {
    return (
        <>
            <Head title="Randevunuz alındı" />
            <div className="flex min-h-screen items-center justify-center bg-stone-50 p-4">
                <div className="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 text-center shadow-sm">
                    <CircleCheck className="mx-auto size-14 text-green-600" />
                    <h1 className="text-xl font-semibold">Randevunuz alındı</h1>
                    <div className="rounded-xl bg-stone-50 p-4 text-sm">
                        <p className="font-medium">{appointment.service?.name}</p>
                        <p>{formatLongDate(appointment.starts_at)}</p>
                        <p className="text-2xl font-semibold tabular-nums">
                            {formatTime(appointment.starts_at)} – {formatTime(appointment.ends_at)}
                        </p>
                    </div>
                    <p className="text-sm text-stone-500">
                        Onay e-postası <strong>{appointment.customer_email}</strong> adresine gönderildi.
                    </p>
                    <div className="flex justify-center gap-4 text-sm">
                        <a href={cancelUrl} className="text-red-600 underline">İptal et</a>
                        <Link href={BookingController.show()} className="underline">Yeni randevu</Link>
                    </div>
                </div>
            </div>
        </>
    );
}
