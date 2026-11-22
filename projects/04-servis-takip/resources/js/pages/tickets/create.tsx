import { Form, Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const deviceTypes = ['Telefon', 'Dizüstü', 'Masaüstü', 'Tablet', 'Akıllı saat', 'Oyun konsolu', 'Diğer'];

function Field({ name, label, error, ...props }: { name: string; label: string; error?: string } & ComponentProps<'input'>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} {...props} />
            <InputError message={error} />
        </div>
    );
}

export default function TicketsCreate() {
    return (
        <>
            <Head title="Yeni servis kaydı" />
            <div className="p-4">
                <Heading title="Yeni servis kaydı" description="Cihazı teslim alırken müşteri ve cihaz bilgilerini girin." />
                <Form {...TicketController.store.form()} className="grid max-w-3xl gap-8 md:grid-cols-2">
                    {({ processing, errors }) => (
                        <>
                            <section className="space-y-4">
                                <h3 className="font-medium">Müşteri</h3>
                                <Field name="customer_phone" label="Telefon" type="tel" placeholder="0532 123 45 67" autoFocus error={errors.customer_phone} />
                                <p className="-mt-2 text-xs text-muted-foreground">Bu numarayla daha önce gelen müşteri varsa kaydı ona bağlanır.</p>
                                <Field name="customer_name" label="Ad soyad" error={errors.customer_name} />
                                <Field name="customer_email" label="E-posta (durum bildirimleri için)" type="email" error={errors.customer_email} />
                            </section>
                            <section className="space-y-4">
                                <h3 className="font-medium">Cihaz</h3>
                                <div className="grid gap-2">
                                    <Label htmlFor="device_type">Cihaz türü</Label>
                                    <select id="device_type" name="device_type" defaultValue="Telefon" className="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                        {deviceTypes.map((t) => <option key={t}>{t}</option>)}
                                    </select>
                                    <InputError message={errors.device_type} />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field name="brand" label="Marka" error={errors.brand} />
                                    <Field name="model" label="Model" error={errors.model} />
                                </div>
                                <Field name="serial_number" label="Seri no / IMEI" error={errors.serial_number} />
                            </section>
                            <section className="space-y-4 md:col-span-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="problem">Arıza açıklaması</Label>
                                    <textarea id="problem" name="problem" rows={3} className="rounded-md border border-input bg-transparent px-3 py-2 text-sm" placeholder="Müşterinin anlattığı şekliyle" />
                                    <InputError message={errors.problem} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field name="accessories" label="Teslim alınan aksesuarlar" placeholder="Adaptör, kılıf..." error={errors.accessories} />
                                    <Field name="estimated_cost" label="Tahmini ücret (₺)" type="number" min={0} error={errors.estimated_cost} />
                                </div>
                                <Button disabled={processing}>Kaydı oluştur</Button>
                            </section>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

TicketsCreate.layout = {
    breadcrumbs: [
        { title: 'Servis kayıtları', href: TicketController.index() },
        { title: 'Yeni kayıt', href: TicketController.create() },
    ],
};
