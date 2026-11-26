<?php

/**
 * Ajans Asistanı eklentisini müşteriye verilecek zip olarak paketler.
 *
 *   php deploy/eklenti-paketle.php --acik-anahtar=<base64> --sunucu=https://lisans.alanadi.com [--cikti=build]
 *
 * Kaynak koddaki class-license.php'de açık anahtar BOŞ, sunucu adresi örnek adrestir: her okur
 * kendi Anahtarcı'sının anahtarını kullanır (sunucuda `php artisan license:keygen` çıktısı).
 * Bu betik iki sabiti YALNIZCA zip'in içinde doldurur; depodaki dosya değişmez.
 *
 * Açık anahtar gizli değildir (zaten her sitede eklentinin içinde duracak); gizli olan,
 * lisans sunucusundaki imza anahtarıdır ve bu betik onu hiç görmez.
 */
$options = getopt('', ['acik-anahtar:', 'sunucu:', 'cikti::']);

function fail(string $message): never
{
    fwrite(STDERR, "✗ $message\n");
    exit(1);
}

// 1. Açık anahtar: base64, çözülünce tam 32 bayt (Ed25519 açık anahtarı)
$key = trim((string) ($options['acik-anahtar'] ?? ''));
$raw = base64_decode($key, true);
if ($key === '' || $raw === false || strlen($raw) !== 32) {
    fail('--acik-anahtar geçerli bir Ed25519 açık anahtarı değil (base64, 32 bayt). Sunucuda: php artisan license:keygen çıktısındaki açık anahtar.');
}

// 2. Lisans sunucusu: http(s) adresi, sonunda / olmadan. PHP sabitine yazılacağı için tırnak ve ters bölü yok.
$server = rtrim(trim((string) ($options['sunucu'] ?? '')), '/');
$scheme = parse_url($server, PHP_URL_SCHEME);
if (! filter_var($server, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true) || strpbrk($server, "'\\") !== false) {
    fail('--sunucu geçerli bir http(s) adresi değil (ör. https://lisans.alanadi.com).');
}
if ($scheme === 'http') {
    fwrite(STDERR, "! Uyarı: sunucu adresi http. Yanıtlar imzalı olduğu için sahtesi kabul edilmez, ama lisans anahtarı ağda açık gider; canlıda https kullanın.\n");
}

$root = dirname(__DIR__);
$plugin = "$root/wp-plugin/ajans-asistani";
$licenseFile = 'includes/class-license.php';

// 3. Sürüm eklenti başlığından: zip adı ve Kumanda paket kütüphanesi aynı sürümü görür
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', (string) file_get_contents("$plugin/ajans-asistani.php"), $m)
    || fail('ajans-asistani.php içinde "Version:" satırı bulunamadı.');
$version = $m[1];

// 4. Sabitler: her biri TAM OLARAK bir kez bulunmalı. Bulunamazsa (dosya değişmişse) sessizce boş anahtarla paketlemek yerine durur.
$source = (string) file_get_contents("$plugin/$licenseFile");
$replacements = [
    "const DEFAULT_PUBLIC_KEY = '';" => "const DEFAULT_PUBLIC_KEY = '$key';",
    "const DEFAULT_SERVER = 'https://lisans.ornek.com';" => "const DEFAULT_SERVER = '$server';",
];
foreach ($replacements as $from => $to) {
    if (substr_count($source, $from) !== 1) {
        fail("$licenseFile içinde şu satır tam bir kez bulunamadı: $from");
    }
    $source = str_replace($from, $to, $source);
}

// 5. Zip: WordPress "Eklenti yükle" ekranı kökte tek klasör bekler (ajans-asistani/...)
$outDir = (string) ($options['cikti'] ?? "$root/build");
is_dir($outDir) || mkdir($outDir, 0755, true) || fail("Klasör açılamadı: $outDir");
$out = rtrim($outDir, '/\\')."/ajans-asistani-$version.zip";

$zip = new ZipArchive;
$zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true || fail("Zip açılamadı: $out");
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS));
$paths = [];
foreach ($files as $file) {
    $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($plugin) + 1));
}
sort($paths);   // sıra sabit: aynı girdi, aynı içerik listesi
foreach ($paths as $path) {
    $path === $licenseFile
        ? $zip->addFromString("ajans-asistani/$path", $source)
        : $zip->addFile("$plugin/$path", "ajans-asistani/$path");
}
$zip->close() || fail("Zip yazılamadı: $out");

echo "✓ $out\n";
echo "  sürüm:       $version\n";
echo "  sunucu:      $server\n";
echo "  açık anahtar: $key\n";
echo '  SHA-256:     '.hash_file('sha256', $out)."\n";
