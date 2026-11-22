import { Form, Head } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import TrackingController from '@/actions/App/Http/Controllers/TrackingController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function TrackingLookup({ business }: { business: string }) {
    return (
        <>
            <Head title="Servis takibi" />
            <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4">
                <div className="w-full max-w-sm space-y-6 rounded-2xl bg-white p-6 shadow-sm">
                    <div className="text-center">
                        <Wrench className="mx-auto mb-2 size-8 text-sky-600" />
                        <h1 className="text-xl font-semibold">{business}</h1>
                        <p className="text-sm text-slate-500">Cihazınızın durumunu sorgulayın</p>
                    </div>
                    <Form {...TrackingController.find.form()} className="space-y-4">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="code">Servis numarası</Label>
                                    <Input id="code" name="code" placeholder="SRV-000042" className="font-mono uppercase" autoFocus />
                                    <InputError message={errors.code} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="phone_last4">Telefonunuzun son 4 hanesi</Label>
                                    <Input id="phone_last4" name="phone_last4" inputMode="numeric" maxLength={4} placeholder="4567" />
                                    <InputError message={errors.phone_last4} />
                                </div>
                                <Button className="w-full" disabled={processing}>Sorgula</Button>
                            </>
                        )}
                    </Form>
                    <p className="text-center text-xs text-slate-400">Servis numarası, cihazı teslim ederken aldığınız fişte yazar.</p>
                </div>
            </div>
        </>
    );
}
