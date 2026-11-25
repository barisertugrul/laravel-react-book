import { Form, Head } from '@inertiajs/react';
import AgencyController from '@/actions/App/Http/Controllers/AgencyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function AgencyCreate() {
    return (
        <>
            <Head title="Yeni ajans" />
            <div className="max-w-xl space-y-6 p-4">
                <Heading title="Yeni ajans" description="Her ajansın siteleri ve ekibi ayrıdır. Ajanslar arasında kenar çubuğundan geçiş yapabilirsiniz." />
                <Form {...AgencyController.store.form()} className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Ajans adı</Label>
                                <Input id="name" name="name" required maxLength={80} autoFocus />
                                <InputError message={errors.name} />
                            </div>
                            <Button disabled={processing}>Oluştur</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AgencyCreate.layout = { breadcrumbs: [{ title: 'Yeni ajans', href: AgencyController.create() }] };
