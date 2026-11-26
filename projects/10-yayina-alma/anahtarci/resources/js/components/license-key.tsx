import { Check, Copy } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

/** Uzun, imzalı anahtarı kırpmadan gösterir ve tek tıkla kopyalatır */
export default function LicenseKey({ value }: { value: string }) {
    const [copied, copy, failed] = useClipboard();
    const code = useRef<HTMLElement>(null);

    // Kopyalanamazsa (ör. düz HTTP) anahtar seçili bırakılır ve kullanıcıya söylenir:
    // sessiz kalsaydık panodaki eski metin yapıştırılırdı (kurulum-sorunlari 37)
    const copyKey = async () => {
        if (!(await copy(value)) && code.current) {
            window.getSelection()?.selectAllChildren(code.current);
        }
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-start gap-2 rounded-lg border bg-muted/40 p-3">
                <code ref={code} className="min-w-0 flex-1 font-mono text-xs break-all select-all">
                    {value}
                </code>
                <Button type="button" size="sm" variant="outline" onClick={copyKey} aria-label="Anahtarı kopyala">
                    {copied === value ? <Check /> : <Copy />}
                    {copied === value ? 'Kopyalandı' : 'Kopyala'}
                </Button>
            </div>
            {failed === value && (
                <p role="alert" className="text-sm text-destructive" data-test="copy-failed">
                    Kopyalanamadı: anahtar seçildi, Ctrl+C ile kopyalayın.
                </p>
            )}
        </div>
    );
}
