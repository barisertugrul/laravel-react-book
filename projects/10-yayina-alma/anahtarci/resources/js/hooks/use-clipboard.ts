// Credit: https://usehooks-ts.com/ (yedek yol ve başarısızlık durumu eklendi)
import { useState } from 'react';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
/** [kopyalanan metin, kopyala, kopyalanamayan metin] */
export type UseClipboardReturn = [CopiedValue, CopyFn, CopiedValue];

/**
 * Pano API'si (navigator.clipboard) yalnızca güvenli bağlamda vardır: HTTPS ya da localhost.
 * Düz HTTP'de (ör. sertifikası henüz olmayan bir alan adı) undefined'dır; o zaman eski yöntem
 * denenir: görünmeyen bir textarea'yı seçip execCommand('copy'). İkisi de olmazsa çağıran
 * bileşen kullanıcıya bunu SÖYLEMELİ: sessiz başarısızlıkta panoda eski metin kalır ve
 * kullanıcı yanlış şeyi yapıştırır (kurulum-sorunlari 37).
 */
async function writeText(text: string): Promise<boolean> {
    if (window.isSecureContext && navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // izin verilmedi ya da sayfa odakta değil: eski yöntemi dene
        }
    }

    // Açık bir pencere (dialog) odağı kendi içinde tutar: textarea onun içine konur
    const host = document.activeElement?.closest('[role="dialog"]') ?? document.body;
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '-9999px';
    const previous = document.activeElement as HTMLElement | null;
    host.appendChild(textarea);
    textarea.select();

    try {
        // Eskimiş ama düz HTTP'de hâlâ tek yol
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        textarea.remove();
        previous?.focus();
    }
}

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);
    const [failedText, setFailedText] = useState<CopiedValue>(null);

    const copy: CopyFn = async (text) => {
        const copied = await writeText(text);
        setCopiedText(copied ? text : null);
        setFailedText(copied ? null : text);

        return copied;
    };

    return [copiedText, copy, failedText];
}
