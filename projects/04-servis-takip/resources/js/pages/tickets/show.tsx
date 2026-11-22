import { Form, Head, router, usePage } from '@inertiajs/react';
import type { Visit } from '@inertiajs/core';
import { Check, Copy, ExternalLink, FileDown, Lock, RotateCcw, Undo2 } from 'lucide-react';
import AssignTicketController from '@/actions/App/Http/Controllers/AssignTicketController';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import TicketReceiptController from '@/actions/App/Http/Controllers/TicketReceiptController';
import TicketStatusController from '@/actions/App/Http/Controllers/TicketStatusController';
import UndoTicketStatusController from '@/actions/App/Http/Controllers/UndoTicketStatusController';
import InputError from '@/components/input-error';
import StatusBadge, { statusLabels } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatDateTime, formatPrice } from '@/lib/format';
import type { Ticket, Transition } from '@/types';

type Props = {
    ticket: Ticket;
    transitions: Transition[];
    can: { update: boolean; assign: boolean; undo: boolean };
    technicians: { id: number; name: string }[];
    undoWindowMinutes: number;
    notifyDelayMinutes: number;
};

export default function TicketsShow({ ticket, transitions, can, technicians, undoWindowMinutes, notifyDelayMinutes }: Props) {
    const [copied, copy] = useClipboard();
    const { auth } = usePage().props;

    // Son olay geri alınabilir mi? (Sunucu da aynı kuralları ayrıca kontrol eder.)
    const lastEvent = ticket.events?.[0];
    const undoable = can.undo && lastEvent?.from_status && lastEvent.reverts_event_id === null;

    /**
     * Müşteriyi etkileyen geçişlerden önce onay sor. false döndürmek isteği iptal eder.
     * <Form> veriyi nesne olarak, dosya varsa FormData olarak gönderir; ikisini de destekliyoruz.
     */
    function confirmTransition(visit: Visit) {
        const data = visit.data as FormData | Record<string, unknown>;
        const value = data instanceof FormData ? data.get('status') : data.status;
        const target = transitions.find((t) => t.value === value);

        if (!target?.confirm) {
            return true;
        }

        return confirm(
            `${ticket.code} "${target.label}" olarak işaretlenecek.\n` +
                `Müşteriye ${notifyDelayMinutes} dakika içinde e-posta gidecek. Emin misiniz?`,
        );
    }

    function undo() {
        if (lastEvent && confirm(`"${statusLabels[lastEvent.to_status]}" değişikliği geri alınsın mı?`)) {
            router.post(UndoTicketStatusController.url(ticket.code), {}, { preserveScroll: true });
        }
    }

    const details: [string, string | null][] = [
        ['Cihaz', `${ticket.device_type} · ${ticket.brand} ${ticket.model ?? ''}`],
        ['Seri no', ticket.serial_number],
        ['Aksesuarlar', ticket.accessories],
        ['Tahmini ücret', formatPrice(ticket.estimated_cost)],
        ['Ücret', formatPrice(ticket.final_cost)],
        ['Geliş', formatDateTime(ticket.created_at)],
    ];

    return (
        <>
            <Head title={ticket.code} />
            <div className="grid gap-6 p-4 lg:grid-cols-[1fr_340px]">
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="font-mono text-2xl font-semibold">{ticket.code}</h1>
                        <StatusBadge status={ticket.status} className="text-sm" />
                        <div className="ml-auto flex gap-2">
                            <Button variant="outline" size="sm" asChild>
                                {/* PDF bir Inertia sayfası değil: normal <a> ile indirilir */}
                                <a href={TicketReceiptController.url(ticket.code)}>
                                    <FileDown /> Servis fişi (PDF)
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => copy(ticket.tracking_url)}>
                                {copied ? <Check /> : <Copy />} Takip bağlantısı
                            </Button>
                            <Button variant="ghost" size="sm" asChild>
                                <a href={ticket.tracking_url} target="_blank" rel="noreferrer">
                                    <ExternalLink /> Müşteri görünümü
                                </a>
                            </Button>
                        </div>
                    </div>

                    <Card>
                        <CardContent className="grid gap-6 sm:grid-cols-2">
                            <div>
                                <p className="text-xs text-muted-foreground uppercase">Müşteri</p>
                                <p className="font-medium">{ticket.customer?.name}</p>
                                <p className="text-sm"><a href={`tel:${ticket.customer?.phone}`}>{ticket.customer?.phone}</a></p>
                                <p className="text-sm text-muted-foreground">{ticket.customer?.email}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground uppercase">Arıza</p>
                                <p className="text-sm">{ticket.problem}</p>
                            </div>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm sm:col-span-2">
                                {details.map(([label, value]) => (
                                    <div key={label} className="contents">
                                        <dt className="text-muted-foreground">{label}</dt>
                                        <dd>{value || '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Durum geçmişi: zaman çizelgesi */}
                    <Card>
                        <CardHeader><CardTitle>Durum geçmişi</CardTitle></CardHeader>
                        <CardContent>
                            <ol className="relative space-y-5 border-l pl-6">
                                {ticket.events?.map((event) => (
                                    <li key={event.id} className="relative">
                                        <span className="absolute top-1.5 -left-[29px] size-3 rounded-full border-2 border-background bg-primary" />
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusBadge status={event.to_status} />
                                            {!event.is_public && (
                                                <span className="flex items-center gap-1 text-xs text-muted-foreground"><Lock className="size-3" /> İç not</span>
                                            )}
                                            <span className="text-xs text-muted-foreground">
                                                {formatDateTime(event.created_at)} · {event.user?.name ?? 'Sistem'}
                                            </span>
                                        </div>
                                        {event.note && <p className="mt-1 text-sm">{event.note}</p>}
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>
                </div>

                <aside className="space-y-6">
                    {undoable && lastEvent && (
                        <Card className="border-amber-300 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30">
                            <CardContent className="space-y-3 text-sm">
                                <p>
                                    Son değişiklik: <strong>{statusLabels[lastEvent.from_status!]} → {statusLabels[lastEvent.to_status]}</strong>
                                    <span className="block text-xs text-muted-foreground">
                                        {lastEvent.user?.name} · {formatDateTime(lastEvent.created_at)}
                                    </span>
                                </p>
                                <Button variant="outline" size="sm" onClick={undo} className="w-full">
                                    <Undo2 /> Son değişikliği geri al
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    {auth.user.role === 'admin'
                                        ? 'Yönetici olarak son değişikliği her zaman geri alabilirsiniz.'
                                        : `Kendi değişikliğinizi ${undoWindowMinutes} dakika içinde geri alabilirsiniz.`}{' '}
                                    Müşteri e-postası henüz gönderilmediyse hiç gönderilmez.
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {can.update && transitions.length > 0 && (
                        <Card>
                            <CardHeader><CardTitle>Durumu güncelle</CardTitle></CardHeader>
                            <CardContent>
                                <Form
                                    {...TicketStatusController.form(ticket.code)}
                                    options={{ preserveScroll: true }}
                                    onBefore={confirmTransition}
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            {/* Yalnızca durum makinesinin izin verdiği geçişler gösterilir */}
                                            <div className="grid gap-2">
                                                {transitions.map((t) => (
                                                    <label key={t.value} className="flex cursor-pointer items-center gap-2 rounded-md border p-2 text-sm has-checked:border-primary has-checked:bg-primary/5">
                                                        <input type="radio" name="status" value={t.value} defaultChecked={t === transitions[0]} />
                                                        {t.value === 'repairing' && ticket.status === 'ready' ? <RotateCcw className="size-3.5" /> : null}
                                                        {statusLabels[ticket.status]} → <strong>{t.label}</strong>
                                                        {t.requires_note && <span className="ml-auto text-xs text-amber-600">gerekçe zorunlu</span>}
                                                    </label>
                                                ))}
                                                <InputError message={errors.status} />
                                            </div>
                                            {transitions.some((t) => t.value === 'ready') && (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="final_cost">Ücret (₺)</Label>
                                                    <Input id="final_cost" name="final_cost" type="number" min={0} defaultValue={ticket.final_cost ?? ticket.estimated_cost ?? ''} />
                                                    <InputError message={errors.final_cost} />
                                                </div>
                                            )}
                                            <div className="grid gap-2">
                                                <Label htmlFor="note">Not</Label>
                                                <textarea id="note" name="note" rows={3} className="rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                                                <InputError message={errors.note} />
                                            </div>
                                            <label className="flex items-center gap-2 text-sm">
                                                <Checkbox name="is_public" value="1" defaultChecked /> Müşteri takip sayfasında görsün
                                            </label>
                                            <Button disabled={processing} className="w-full">Kaydet</Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader><CardTitle>Teknisyen</CardTitle></CardHeader>
                        <CardContent>
                            {can.assign ? (
                                <select
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                    value={ticket.assigned_to ?? ''}
                                    onChange={(e) =>
                                        router.patch(AssignTicketController.url(ticket.code), { assigned_to: e.target.value || null }, { preserveScroll: true })
                                    }
                                >
                                    <option value="">Atanmadı</option>
                                    {technicians.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                </select>
                            ) : (
                                <p className="text-sm">{ticket.technician?.name ?? 'Atanmadı: durumu ilerleten ilk teknisyen sahiplenir.'}</p>
                            )}
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </>
    );
}

TicketsShow.layout = {
    breadcrumbs: [
        { title: 'Servis kayıtları', href: TicketController.index() },
        { title: 'Kayıt', href: TicketController.index() },
    ],
};
