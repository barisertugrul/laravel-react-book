import { Form, Head } from '@inertiajs/react';
import { PlugZap } from 'lucide-react';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

export default function SiteCreate() {
    return (
        <>
            <Head title="Site ekle" />
            <div className="grid gap-6 p-4 lg:grid-cols-5">
                <div className="space-y-6 lg:col-span-3">
                    <Heading title="Site ekle" description="WordPress sitesini bağlantı anahtarıyla panele bağlayın." />

                    <Form {...SiteController.store.form()} disableWhileProcessing className="space-y-5">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Site adı</Label>
                                    <Input id="name" name="name" required maxLength={80} placeholder="Ör. Lale Kafe" />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="connection_key">Bağlantı anahtarı</Label>
                                    <textarea
                                        id="connection_key"
                                        name="connection_key"
                                        required
                                        rows={5}
                                        spellCheck={false}
                                        placeholder="AA1.eyJ1Ijoi…"
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs break-all shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    />
                                    <InputError message={errors.connection_key} />
                                </div>

                                <Button disabled={processing} data-test="pair-site-button">
                                    {processing ? <Spinner /> : <PlugZap />} Bağlan
                                </Button>
                            </>
                        )}
                    </Form>
                </div>

                <aside className="h-fit space-y-3 rounded-xl border p-4 text-sm lg:col-span-2">
                    <h2 className="font-medium">Bağlantı anahtarı nereden alınır?</h2>
                    <ol className="list-decimal space-y-2 pl-5 text-muted-foreground">
                        <li>WordPress sitesine <strong className="text-foreground">Ajans Asistanı</strong> eklentisini kurup etkinleştirin.</li>
                        <li>
                            <strong className="text-foreground">Ayarlar → Ajans Asistanı</strong> sayfasında “Bağlantı anahtarı oluştur” düğmesine basın.
                        </li>
                        <li>Anahtarı kopyalayıp buraya yapıştırın. Anahtar tek kullanımlıktır ve 15 dakika geçerlidir.</li>
                    </ol>
                    <p className="text-muted-foreground">
                        Bağlanırken panel bu siteye özel bir anahtar çifti üretir; bundan sonraki her istek ve yanıt imzalıdır.
                    </p>
                </aside>
            </div>
        </>
    );
}

SiteCreate.layout = {
    breadcrumbs: [
        { title: 'Siteler', href: dashboard() },
        { title: 'Site ekle', href: SiteController.create() },
    ],
};
