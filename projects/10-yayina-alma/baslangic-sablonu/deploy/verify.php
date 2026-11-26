<?php

/**
 * SUNUCUDA, paket açılmadan ÖNCE çalışır (deploy.sh çağırır). Çıkış kodu 0: imza geçerli.
 *
 *   php verify.php <paket.tar.gz> <açık-anahtar-dosyası>
 *
 * Açık anahtar paketin İÇİNDEN okunmaz: paketi değiştiren kişi içine kendi anahtarını da koyabilirdi.
 * Sunucudaki kopya (shared/deploy.pub) yalnızca yönetici tarafından konur.
 */
// sodium uzantısı yoksa (ör. Windows'ta php.new) paragonie/sodium_compat (require-dev) aynı fonksiyonları sağlar
if (! function_exists('sodium_crypto_sign_detached') && is_file(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
}

[$self, $package, $publicFile] = $argv + [null, null, null];

if ($package === null || $publicFile === null) {
    fwrite(STDERR, "Kullanım: php verify.php <paket.tar.gz> <deploy.pub>\n");
    exit(2);
}

foreach ([$package, $package.'.sig', $publicFile] as $file) {
    if (! is_file($file)) {
        fwrite(STDERR, "✗ Bulunamadı: {$file}\n");
        exit(1);
    }
}

$public = base64_decode(trim(file_get_contents($publicFile)), true);
$signature = base64_decode(trim(file_get_contents($package.'.sig')), true);

if ($public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
    || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
    fwrite(STDERR, "✗ Açık anahtar ya da imza bozuk\n");
    exit(1);
}

$message = "laravel-deploy-v1\n".hash_file('sha256', $package);

if (! sodium_crypto_sign_verify_detached($signature, $message, $public)) {
    fwrite(STDERR, "✗ İmza geçersiz: paket bu anahtarla imzalanmamış ya da yolda değişmiş\n");
    exit(1);
}

echo "✓ İmza geçerli\n";
