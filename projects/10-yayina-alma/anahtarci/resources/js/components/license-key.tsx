import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

/** Uzun, imzalı anahtarı kırpmadan gösterir ve tek tıkla kopyalatır */
export default function LicenseKey({ value }: { value: string }) {
    const [copied, copy] = useClipboard();

    return (
        <div className="flex items-start gap-2 rounded-lg border bg-muted/40 p-3">
            <code className="min-w-0 flex-1 font-mono text-xs break-all select-all">{value}</code>
            <Button type="button" size="sm" variant="outline" onClick={() => copy(value)} aria-label="Anahtarı kopyala">
                {copied === value ? <Check /> : <Copy />}
                {copied === value ? 'Kopyalandı' : 'Kopyala'}
            </Button>
        </div>
    );
}
