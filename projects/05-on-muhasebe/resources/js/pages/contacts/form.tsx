import { Form, Head } from '@inertiajs/react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import FormField, { selectClass } from '@/components/form-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Contact, Option } from '@/types';

/** Aynı form hem yeni cari hem düzenleme için: contact varsa düzenleme kipindeyiz. */
export default function ContactForm({
    contact,
    types,
}: {
    contact?: Contact;
    types: Option[];
}) {
    const action = contact
        ? ContactController.update.form(contact.id)
        : ContactController.store.form();
    const title = contact ? `${contact.name} — düzenle` : 'Yeni cari';

    return (
        <>
            <Head title={title} />
            <div className="p-4">
                <Heading
                    title={title}
                    description="Fatura kesilecek müşteri veya alış yapılan tedarikçi."
                />
                <Form
                    {...action}
                    className="grid max-w-2xl gap-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="type">Tür</Label>
                                <select
                                    id="type"
                                    name="type"
                                    defaultValue={contact?.type ?? 'customer'}
                                    className={selectClass}
                                >
                                    {types.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.type} />
                            </div>
                            <FormField
                                name="name"
                                label="Ad / unvan"
                                defaultValue={contact?.name}
                                autoFocus
                                error={errors.name}
                            />
                            <FormField
                                name="tax_number"
                                label="Vergi no / TC kimlik no"
                                inputMode="numeric"
                                defaultValue={contact?.tax_number ?? ''}
                                error={errors.tax_number}
                            />
                            <FormField
                                name="tax_office"
                                label="Vergi dairesi"
                                defaultValue={contact?.tax_office ?? ''}
                                error={errors.tax_office}
                            />
                            <FormField
                                name="phone"
                                label="Telefon"
                                type="tel"
                                defaultValue={contact?.phone ?? ''}
                                error={errors.phone}
                            />
                            <FormField
                                name="email"
                                label="E-posta"
                                type="email"
                                defaultValue={contact?.email ?? ''}
                                error={errors.email}
                            />
                            <div className="sm:col-span-2">
                                <FormField
                                    name="address"
                                    label="Adres"
                                    defaultValue={contact?.address ?? ''}
                                    error={errors.address}
                                />
                            </div>
                            <div>
                                <Button disabled={processing}>
                                    {contact ? 'Kaydet' : 'Cariyi oluştur'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ContactForm.layout = {
    breadcrumbs: [{ title: 'Cariler', href: ContactController.index() }],
};
