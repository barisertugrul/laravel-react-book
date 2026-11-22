import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/datetime';
import type { Service } from '@/types';

/** Bölüm 1'deki kategori sayfasıyla aynı desen: tek form, ekleme ve düzenleme. */
export default function ServicesIndex({ services }: { services: Service[] }) {
    const [editing, setEditing] = useState<Service | null>(null);
    const formProps = editing ? ServiceController.update.form(editing.id) : ServiceController.store.form();

    return (
        <>
            <Head title="Hizmetler" />
            <div className="flex flex-1 flex-col gap-6 p-4 lg:flex-row lg:items-start">
                <Card className="lg:w-80 lg:shrink-0">
                    <CardHeader>
                        <CardTitle>{editing ? 'Hizmeti düzenle' : 'Yeni hizmet'}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form key={editing?.id ?? 'new'} {...formProps} resetOnSuccess onSuccess={() => setEditing(null)} className="space-y-4">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Ad</Label>
                                        <Input id="name" name="name" defaultValue={editing?.name} placeholder="Saç Kesimi" />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="duration">Süre (dk)</Label>
                                            <Input id="duration" name="duration" type="number" step={15} min={15} defaultValue={editing?.duration ?? 30} />
                                            <InputError message={errors.duration} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="price">Fiyat (₺)</Label>
                                            <Input id="price" name="price" type="number" min={0} defaultValue={editing?.price} />
                                            <InputError message={errors.price} />
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox id="is_active" name="is_active" value="1" defaultChecked={editing?.is_active ?? true} />
                                        <Label htmlFor="is_active">Randevuya açık</Label>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button disabled={processing}>{editing ? 'Güncelle' : 'Ekle'}</Button>
                                        {editing && (
                                            <Button type="button" variant="ghost" onClick={() => setEditing(null)}>
                                                <X /> Vazgeç
                                            </Button>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="flex-1 space-y-4">
                    <Heading title="Hizmetler" description="Müşterilerin seçebileceği hizmetler, süreleri ve fiyatları." />
                    <ul className="divide-y rounded-xl border">
                        {services.map((service) => (
                            <li key={service.id} className="flex items-center gap-3 p-3">
                                <span className="flex-1 font-medium">{service.name}</span>
                                <span className="text-sm text-muted-foreground">{service.duration} dk</span>
                                <span className="w-20 text-right text-sm tabular-nums">{formatPrice(service.price)}</span>
                                <Badge variant="secondary">{service.appointments_count} randevu</Badge>
                                {!service.is_active && <Badge variant="outline">Pasif</Badge>}
                                <Button size="icon" variant="ghost" aria-label="Düzenle" onClick={() => setEditing(service)}>
                                    <Pencil />
                                </Button>
                                <Button size="icon" variant="ghost" aria-label="Sil" asChild>
                                    <Link href={ServiceController.destroy(service.id)} as="button" onBefore={() => confirm(`"${service.name}" silinsin mi?`)}>
                                        <Trash2 className="text-destructive" />
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </>
    );
}

ServicesIndex.layout = {
    breadcrumbs: [{ title: 'Hizmetler', href: ServiceController.index() }],
};
