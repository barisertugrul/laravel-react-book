<?php
/**
 * Plugin Name:       Form Kalesi
 * Description:       Yorum formuna görünmez bir tuzak alanı (honeypot) ekler; alanı dolduran botların yorumları reddedilir. Bölüm 9'daki paket kütüphanesi örneği.
 * Version:           2.5.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Laravel + React ile Gerçek Projeler
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// İnsanlar görmez (CSS ile gizli), botlar her alanı doldurur
add_action(
	'comment_form_after_fields',
	function (): void {
		echo '<p style="position:absolute;left:-9999px" aria-hidden="true"><label>Web sitesi 2<input type="text" name="fk_website" tabindex="-1" autocomplete="off"></label></p>';
	}
);

add_filter(
	'preprocess_comment',
	function ( array $comment ): array {
		if ( ! empty( $_POST['fk_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- yorum formunun kendi akışı
			wp_die( 'Yorumunuz otomatik gönderim olarak algılandı.', 'Form Kalesi', array( 'response' => 403 ) );
		}

		return $comment;
	}
);
