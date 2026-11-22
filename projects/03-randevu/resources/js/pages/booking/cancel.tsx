import { Form, Head, Link } from '@inertiajs/react';
import BookingController from '@/actions/App/Http/Controllers/BookingController';
import { Button } from '@/components/ui/button';
import { formatLongDate, formatTime } from '@/lib/datetime';
import type { Appointment } from '@/types';

export default function BookingCancel({ appointment, action }: { appointment: Appointment; action: string }) {
    const cancelled = appointment.status === 'cancelled';

    return (
        <>
            <Head title="Randevu iptali" />
            <div className="flex min-h-screen items-center justify-center bg-stone-50 p-4">
                <div className="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 text-center shadow-sm">
                    <h1 className="text-xl font-semibold">{cancelled ? 'Randevunuz iptal edildi' : 'Randevunuzu iptal edin'}</h1>
                    <div className="rounded-xl bg-stone-50 p-4 text-sm">
                        <p className="font-medium">{appointment.service?.name}</p>
                        <p>{formatLongDate(appointment.starts_at)}, {formatTime(appointment.starts_at)}</p>
                    </div>
                    {cancelled ? (
                        <Link href={BookingController.show()} className="text-sm underline">Yeni randevu alın</Link>
                    ) : (
                        // action: imzayı (signature) içeren adres. İmza olmadan istek 403 ile reddedilir.
                        <Form action={action} method="post">
                            {({ processing }) => (
                                <Button variant="destructive" className="w-full" disabled={processing}>
                                    Evet, iptal et
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            </div>
        </>
    );
}
