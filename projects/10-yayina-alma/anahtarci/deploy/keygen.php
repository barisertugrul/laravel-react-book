<?php

/**
 * Dağıtım paketlerini imzalayan Ed25519 anahtar çiftini üretir. BİR KEZ, kendi bilgisayarınızda çalıştırın.
 *
 *   php deploy/keygen.php
 *
 * Gizli anahtar  → depo DIŞINA: ~/.deploy-keys/laravel-react-book.key (Windows'ta %USERPROFILE%)
 *                  İçeriğini GitHub'da Settings → Secrets and variables → Actions → DEPLOY_SIGNING_KEY olarak kaydedin.
 * Açık anahtar   → deploy/deploy.pub (depoya girer; Kumanda ve Anahtarcı aynı anahtarı kullanır; sunucuda shared/deploy.pub olarak da durur)
 */
// sodium uzantısı yoksa (ör. Windows'ta php.new) projenin sodium_compat'ı aynı fonksiyonları sağlar (Bölüm 7)
if (! function_exists('sodium_crypto_sign_detached') && is_file(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
}

$home = getenv('USERPROFILE') ?: getenv('HOME');
$dir = $home.DIRECTORY_SEPARATOR.'.deploy-keys';
$secretFile = $dir.DIRECTORY_SEPARATOR.'laravel-react-book.key';
$publicFile = __DIR__.DIRECTORY_SEPARATOR.'deploy.pub';

if (file_exists($secretFile)) {
    // Anahtar zaten var (ör. ikinci proje): yenisi üretilmez, açık anahtar gizli anahtardan türetilir
    $secret = base64_decode(trim(file_get_contents($secretFile)), true);
    file_put_contents($publicFile, base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)).PHP_EOL);
    echo "Anahtar zaten var ({$secretFile}); açık anahtar yazıldı: {$publicFile}\n";
    exit(0);
}

$pair = sodium_crypto_sign_keypair();

if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}
file_put_contents($secretFile, base64_encode(sodium_crypto_sign_secretkey($pair)).PHP_EOL);
chmod($secretFile, 0600);
file_put_contents($publicFile, base64_encode(sodium_crypto_sign_publickey($pair)).PHP_EOL);

echo "Gizli anahtar: {$secretFile}\n";
echo "Açık anahtar:  {$publicFile}\n";
echo "Sonraki adım: gizli anahtar dosyasının içeriğini GitHub'a DEPLOY_SIGNING_KEY secret'ı olarak ekleyin.\n";
