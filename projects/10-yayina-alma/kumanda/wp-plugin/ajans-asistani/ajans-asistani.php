<?php
/**
 * Plugin Name:       Ajans Asistanı
 * Description:       Siteyi Kumanda ajans paneline bağlar: sağlık raporu, sürümler, eklenti kurma ve güncelleme. Tüm iletişim Ed25519 ile imzalıdır.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Laravel + React ile Gerçek Projeler
 * License:           GPL-2.0-or-later
 * Text Domain:       ajans-asistani
 */

defined( 'ABSPATH' ) || exit;

define( 'AJANS_ASISTANI_VERSION', '1.2.0' );

// sodium uzantısı yoksa WordPress'in çekirdekte taşıdığı sodium_compat devreye girer (WP 5.2+)
if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
	require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
}

require_once __DIR__ . '/includes/class-protocol.php';
require_once __DIR__ . '/includes/class-keys.php';
require_once __DIR__ . '/includes/class-report.php';
require_once __DIR__ . '/includes/class-commands.php';
require_once __DIR__ . '/includes/class-license.php';
require_once __DIR__ . '/includes/class-rest.php';
require_once __DIR__ . '/includes/class-admin.php';

// Etkinleştirmede bu siteye özel anahtar çifti üretilir (zaten varsa dokunulmaz)
register_activation_hook( __FILE__, array( 'Ajans_Asistani_Keys', 'ensure_keypair' ) );

// Devre dışı bırakılınca lisans sunucusundaki yer boşaltılır (site taşınacaksa hak boşa harcanmasın)
register_deactivation_hook( __FILE__, array( 'Ajans_Asistani_License', 'deactivate' ) );

Ajans_Asistani_Rest::boot();
Ajans_Asistani_License::boot();

if ( is_admin() ) {
	Ajans_Asistani_Admin::boot();
}
