<?php

namespace App\Support;

use App\Models\Card;

/**
 * Bir kartı vCard 3.0 biçimine çevirir.
 * Telefonda açılan .vcf dosyası, kişiyi tek dokunuşla rehbere ekler.
 */
class VCard
{
    public static function fromCard(Card $card): string
    {
        [$first, $last] = self::splitName($card->full_name);

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:'.self::escape($last).';'.self::escape($first).';;;',
            'FN:'.self::escape($card->full_name),
        ];

        $optional = [
            'ORG' => $card->company,
            'TITLE' => $card->title,
            'TEL;TYPE=CELL' => $card->phone,
            'EMAIL;TYPE=INTERNET' => $card->email,
            'URL' => $card->website,
            'NOTE' => $card->bio,
        ];

        foreach ($optional as $key => $value) {
            if (filled($value)) {
                $lines[] = $key.':'.self::escape($value);
            }
        }

        if (filled($card->address)) {
            $lines[] = 'ADR;TYPE=WORK:;;'.self::escape($card->address).';;;;';
        }

        foreach ($card->links as $link) {
            $lines[] = 'URL;TYPE='.self::escape(mb_strtoupper($link->label)).':'.$link->url;
        }

        $lines[] = 'END:VCARD';

        // vCard standardı satır sonu olarak CRLF ister.
        return implode("\r\n", $lines)."\r\n";
    }

    /** "Ali Rıza Yılmaz" → ["Ali Rıza", "Yılmaz"] */
    public static function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));
        $last = count($parts) > 1 ? array_pop($parts) : '';

        return [implode(' ', $parts), $last];
    }

    /** vCard'da özel anlamı olan karakterleri kaçışlar. */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value,
        );
    }
}
