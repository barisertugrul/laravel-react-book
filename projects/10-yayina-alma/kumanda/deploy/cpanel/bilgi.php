<?php

/**
 * Tek kullanımlık: web tarafındaki PHP'nin nasıl çalıştığını gösterir, sonra KENDİNİ SİLER.
 * phpinfo() kullanılmaz: gereğinden fazlasını (ortam değişkenleri, yollar) açığa çıkarır.
 */
header('Content-Type: application/json; charset=utf-8');

$keys = ['opcache.enable', 'opcache.validate_timestamps', 'opcache.revalidate_freq', 'opcache.revalidate_path',
    'realpath_cache_ttl', 'memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size', 'open_basedir'];

echo json_encode([
    'php' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'kullanici' => function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? posix_geteuid()) : get_current_user(),
    'sunucu' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'belge_koku' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    'https' => $_SERVER['HTTPS'] ?? null,
    'uzak_adres' => $_SERVER['REMOTE_ADDR'] ?? null,
    'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
    'ayarlar' => array_combine($keys, array_map('ini_get', $keys)),
    'opcache_calisiyor' => function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false),
    'opcache_reset_var' => function_exists('opcache_reset'),
    'eklentiler' => array_values(array_intersect(get_loaded_extensions(), ['sodium', 'pdo_mysql', 'mbstring', 'intl', 'zip', 'gd', 'curl', 'fileinfo', 'bcmath', 'redis'])),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

unlink(__FILE__);
