<?php

/**
 * Paketi imzalar: <paket>.sig dosyasını yazar.
 *
 *   php deploy/sign.php build/uygulama-wsl-20261125120000.tar.gz
 *
 * Gizli anahtar sırasıyla: DEPLOY_SIGNING_KEY ortam değişkeni (CI'da GitHub secret'ı)
 * ya da ~/.deploy-keys/<DEPLOY_KEY_NAME>.key, varsayılan uygulama.key (yerel derleme, bkz. keygen.php).
 *
 * İmzalanan şey dosyanın kendisi değil, bağlamlı özeti: "laravel-deploy-v1\n" + SHA-256.
 * Bağlam öneki, bu anahtarla atılmış bir imzanın başka bir amaçla yeniden kullanılmasını önler.
 */
// sodium uzantısı yoksa (ör. Windows'ta php.new) paragonie/sodium_compat (require-dev) aynı fonksiyonları sağlar
if (! function_exists('sodium_crypto_sign_detached') && is_file(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
}

$package = $argv[1] ?? null;

if ($package === null || ! is_file($package)) {
    fwrite(STDERR, "Kullanım: php deploy/sign.php <paket.tar.gz>\n");
    exit(1);
}

$secret = getenv('DEPLOY_SIGNING_KEY') ?: null;

if ($secret === null) {
    $home = getenv('USERPROFILE') ?: getenv('HOME');
    $file = $home.DIRECTORY_SEPARATOR.'.deploy-keys'.DIRECTORY_SEPARATOR.(getenv('DEPLOY_KEY_NAME') ?: 'uygulama').'.key';
    $secret = is_file($file) ? file_get_contents($file) : null;
}

if ($secret === null) {
    fwrite(STDERR, "İmza anahtarı yok: DEPLOY_SIGNING_KEY tanımlayın ya da php deploy/keygen.php çalıştırın.\n");
    exit(1);
}

$key = base64_decode(trim($secret), true);

if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    fwrite(STDERR, "İmza anahtarı geçersiz (64 baytlık Ed25519 gizli anahtarı, base64).\n");
    exit(1);
}

$message = "laravel-deploy-v1\n".hash_file('sha256', $package);
file_put_contents($package.'.sig', base64_encode(sodium_crypto_sign_detached($message, $key)).PHP_EOL);
if (extension_loaded('sodium')) {
    sodium_memzero($key); // sodium_compat bunu desteklemez: PHP belleği güvenle silemez
}

echo '✓ İmzalandı: '.basename($package).".sig\n";
