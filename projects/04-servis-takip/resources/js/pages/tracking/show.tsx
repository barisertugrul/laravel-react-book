import { Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import StatusBadge from '@/components/status-badge';
import { formatDateTime, formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TicketStatus } from '@/types';

type Props = {
    business: string;
    ticket: {
        code: string;
        device: string;
        status: TicketStatus;
        status_label: string;
        estimated_cost: string | null;
        final_cost: string | null;
        created_at: string;
    };
    events: { id: number; label: string; status: TicketStatus; note: string | null; created_at: string }[];
};

/** Müşterinin gördüğü ilerleme çubuğundaki adımlar (iptal ayrı gösterilir) */
const steps: { status: TicketStatus; label: string }[] = [
    { status: 'received', label: 'Teslim alındı' },
    { status: 'diagnosing', label: 'İnceleniyor' },
    { status: 'repairing', label: 'Tamir' },
    { status: 'ready', label: 'Hazır' },
    { status: 'delivered', label: 'Teslim' },
];
const order: TicketStatus[] = ['received', 'diagnosing', 'waiting_approval', 'repairing', 'ready', 'delivered'];

export default function TrackingShow({ business, ticket, events }: Props) {
    const current = order.indexOf(ticket.status);
    const cancelled = ticket.status === 'cancelled';

    return (
        <>
            <Head title={`${ticket.code} - Servis durumu`} />
            <div className="min-h-screen bg-slate-50 px-4 py-8">
                <div className="mx-auto max-w-md space-y-6">
                    <p className="text-center text-sm text-slate-500">{business}</p>

                    <div className="rounded-2xl bg-white p-6 shadow-sm">
                        <p className="font-mono text-sm text-slate-500">{ticket.code}</p>
                        <h1 className="text-lg font-semibold">{ticket.device}</h1>
                        <StatusBadge status={ticket.status} className="mt-2 text-sm" />

                        {!cancelled && (
                            <ol className="mt-6 flex justify-between">
                                {steps.map((step) => {
                                    const done = order.indexOf(step.status) <= current;
                                    return (
                                        <li key={step.status} className="flex flex-1 flex-col items-center gap-1 text-center text-[11px]">
                                            <span className={cn('flex size-7 items-center justify-center rounded-full border-2', done ? 'border-green-600 bg-green-600 text-white' : 'border-slate-300 text-slate-300')}>
                                                {done && <Check className="size-4" />}
                                            </span>
                                            <span className={done ? 'font-medium' : 'text-slate-400'}>{step.label}</span>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}

                        {(ticket.final_cost || ticket.estimated_cost) && (
                            <p className="mt-6 rounded-lg bg-slate-50 p-3 text-sm">
                                {ticket.final_cost ? 'Ücret' : 'Tahmini ücret'}:{' '}
                                <strong>{formatPrice(ticket.final_cost ?? ticket.estimated_cost)}</strong>
                            </p>
                        )}
                    </div>

                    <div className="rounded-2xl bg-white p-6 shadow-sm">
                        <h2 className="mb-4 font-semibold">Gelişmeler</h2>
                        <ol className="space-y-4">
                            {events.map((event) => (
                                <li key={event.id} className="border-l-2 border-slate-200 pl-4">
                                    <p className="text-sm font-medium">{event.label}</p>
                                    {event.note && <p className="text-sm text-slate-600">{event.note}</p>}
                                    <p className="text-xs text-slate-400">{formatDateTime(event.created_at)}</p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>
            </div>
        </>
    );
}
