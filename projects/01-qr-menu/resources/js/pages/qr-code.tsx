import { Head } from '@inertiajs/react';
import { Check, Copy, Download, ExternalLink } from 'lucide-react';
import QrCodeController from '@/actions/App/Http/Controllers/QrCodeController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useClipboard } from '@/hooks/use-clipboard';

export default function QrCodePage({
    menuUrl,
    svg,
}: {
    menuUrl: string;
    svg: string;
}) {
    const [copiedText, copy] = useClipboard();

    return (
        <>
            <Head title="QR Kod" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Menü QR kodu"
                    description="Bu kodu yazdırıp masalara yerleştirin. Okutan müşteri doğrudan menünüze gider."
                />
                <Card className="max-w-md">
                    <CardContent className="flex flex-col items-center gap-4">
                        {/* SVG'yi sunucu ürettiği için güvenle HTML olarak basabiliriz. */}
                        <div
                            className="rounded-lg bg-white p-2"
                            dangerouslySetInnerHTML={{ __html: svg }}
                        />
                        <code className="rounded bg-muted px-2 py-1 text-sm break-all">
                            {menuUrl}
                        </code>
                        <div className="flex flex-wrap justify-center gap-2">
                            <Button asChild>
                                <a href={QrCodeController.download().url}>
                                    <Download /> SVG indir
                                </a>
                            </Button>
                            <Button variant="outline" onClick={() => copy(menuUrl)}>
                                {copiedText === menuUrl ? <Check /> : <Copy />}
                                Bağlantıyı kopyala
                            </Button>
                            <Button variant="ghost" asChild>
                                <a href={menuUrl} target="_blank" rel="noreferrer">
                                    <ExternalLink /> Menüyü aç
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

QrCodePage.layout = {
    breadcrumbs: [{ title: 'QR Kod', href: QrCodeController.show() }],
};
