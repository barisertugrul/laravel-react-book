<?php

namespace App\Packages;

use RuntimeException;
use ZipArchive;

/**
 * Yüklenen zip gerçekten bir WordPress eklentisi mi? Adı, sürümü ve klasörü nedir?
 *
 * WordPress'in beklediği düzen:  eklenti-adi/
 *                                  eklenti-adi.php   ← başlık yorumu: Plugin Name, Version
 *                                  includes/...
 * Başlık, WordPress'in get_file_data() fonksiyonundaki kuralla okunur: dosyanın ilk 8 KB'ı,
 * satır başındaki yorum işaretlerinden sonra "Plugin Name:".
 */
final class PluginZip
{
    /** @return array{slug: string, name: string, version: string} */
    public static function inspect(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip uzantısı kapalı: php.ini içinde extension=zip satırını açın.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Dosya geçerli bir zip değil.');
        }

        try {
            $folders = [];
            $candidates = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);

                // "../" ile zip dışına yazmaya çalışan girdiler (zip slip): böyle bir paket hiç kabul edilmez
                if (str_contains($entry, '..') || str_starts_with($entry, '/')) {
                    throw new RuntimeException('Zip içinde geçersiz bir dosya yolu var.');
                }

                $folders[strtok($entry, '/')] = true;

                // Yalnızca klasörün hemen içindeki .php dosyaları başlık taşıyabilir
                if (preg_match('#^[^/]+/[^/]+\.php$#i', $entry)) {
                    $candidates[] = $entry;
                }
            }

            if (count($folders) !== 1) {
                throw new RuntimeException('Zip, eklentinin tek bir klasörünü içermeli (ör. eklenti-adi/eklenti-adi.php).');
            }

            foreach ($candidates as $entry) {
                $header = self::header((string) $zip->getFromName($entry, 8192));

                if ($header['name'] !== '') {
                    if ($header['version'] === '') {
                        throw new RuntimeException("{$entry} dosyasında Version satırı yok.");
                    }

                    return ['slug' => (string) strtok($entry, '/'), ...$header];
                }
            }

            throw new RuntimeException('Zip içinde "Plugin Name:" başlığı olan bir PHP dosyası bulunamadı.');
        } finally {
            $zip->close();
        }
    }

    /** @return array{name: string, version: string} */
    private static function header(string $code): array
    {
        $read = function (string $field) use ($code): string {
            return preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*'.preg_quote($field, '/').':(.*)$/mi', $code, $m)
                ? trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $m[1]))
                : '';
        };

        return ['name' => $read('Plugin Name'), 'version' => $read('Version')];
    }
}
