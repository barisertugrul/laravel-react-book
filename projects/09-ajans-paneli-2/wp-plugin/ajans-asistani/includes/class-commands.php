<?php
/**
 * Panelin gönderdiği komutlar: eklenti kurma ve güncelleme.
 * Rest sınıfı isteğin imzasını doğruladıktan SONRA buraya gelinir; burada yalnızca iş yapılır.
 *
 * Kaynaklar:
 *  wporg   — wordpress.org eklenti dizini (yalnızca kısa ad: "contact-form-7")
 *  package — panelin paket kütüphanesi: süreli imzalı adres + SHA-256 özeti
 */

defined( 'ABSPATH' ) || exit;

final class Ajans_Asistani_Commands {

	public static function install( array $data ): WP_REST_Response {
		self::prepare();

		$source = (string) ( $data['source'] ?? '' );
		$temp   = null;

		if ( 'wporg' === $source ) {
			$slug = sanitize_key( (string) ( $data['slug'] ?? '' ) );
			$info = $slug ? plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) ) : null;

			if ( ! $info || is_wp_error( $info ) || empty( $info->download_link ) ) {
				return self::error( 404, 'not_found', "wordpress.org üzerinde böyle bir eklenti yok: {$slug}" );
			}

			$package = $info->download_link;
		} elseif ( 'package' === $source ) {
			// Ajans Asistanı'nın kendi yeni sürümü: lisans ömür boyu, güncellemeler süreli (Bölüm 7)
			if ( Ajans_Asistani_License::PRODUCT === ( $data['slug'] ?? '' ) && ! Ajans_Asistani_License::updates_allowed() ) {
				return self::error( 403, 'license_required', 'Ajans Asistanı güncellemesi için geçerli bir lisans ve süresi dolmamış güncelleme hakkı gerekir.' );
			}

			$temp = self::download( (string) ( $data['url'] ?? '' ), strtolower( (string) ( $data['sha256'] ?? '' ) ) );

			if ( $temp instanceof WP_REST_Response ) {
				return $temp; // indirme ya da özet hatası
			}

			$package = $temp;
		} else {
			return self::error( 400, 'invalid_source', 'Kaynak "wporg" ya da "package" olmalı.' );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		// overwrite_package: eklenti zaten kuruluysa üzerine yaz (paketten güncelleme de bu yoldan yapılır)
		$result = $upgrader->install( $package, array( 'overwrite_package' => true ) );

		if ( $temp ) {
			wp_delete_file( $temp );
		}

		$failure = self::failure( $result, $skin );
		if ( $failure ) {
			return self::error( 422, 'install_failed', $failure );
		}

		$file = $upgrader->plugin_info();

		if ( ! $file ) {
			return self::error( 422, 'install_failed', 'Paket kuruldu ama içinde eklenti başlığı (Plugin Name) bulunamadı.' );
		}

		if ( ! empty( $data['activate'] ) && ! is_plugin_active( $file ) ) {
			$activated = activate_plugin( $file );

			if ( is_wp_error( $activated ) ) {
				return self::error( 422, 'activation_failed', 'Kuruldu ama etkinleştirilemedi: ' . $activated->get_error_message() );
			}
		}

		return new WP_REST_Response( self::state( $file ), 200 );
	}

	/** wordpress.org'daki yeni sürüme güncelleme */
	public static function update( array $data ): WP_REST_Response {
		self::prepare();

		$file = (string) ( $data['file'] ?? '' );

		if ( ! array_key_exists( $file, get_plugins() ) ) {
			return self::error( 404, 'not_installed', "Eklenti bu sitede kurulu değil: {$file}" );
		}

		$was_active = is_plugin_active( $file );

		wp_update_plugins(); // güncelleme bilgisini tazele: panel, sitenin son raporundan daha yeni bir şey biliyor olabilir

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $file );

		$failure = self::failure( $result, $skin );
		if ( $failure ) {
			return self::error( 422, 'update_failed', $failure );
		}

		// upgrade() güncellenecek eklentiyi devre dışı bırakır; geri açmak çağıranın işidir
		if ( $was_active && ! is_plugin_active( $file ) ) {
			activate_plugin( $file );
		}

		return new WP_REST_Response( self::state( $file ), 200 );
	}

	/** Panelden toplu lisans etkinleştirme: anahtar imzalı istekle gelir */
	public static function activate_license( array $data ): WP_REST_Response {
		$result = Ajans_Asistani_License::activate( (string) ( $data['key'] ?? '' ) );

		if ( is_wp_error( $result ) ) {
			// Lisans sunucusunun kodu olduğu gibi panele iletilir (ör. site_limit_reached)
			return self::error( 422, $result->get_error_code(), $result->get_error_message() );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public static function deactivate_license( array $data ): WP_REST_Response {
		Ajans_Asistani_License::deactivate();

		return new WP_REST_Response( Ajans_Asistani_License::status(), 200 );
	}

	/** Kurulum/güncelleme için wp-admin dosyaları ve yeterli süre */
	private static function prepare(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
	}

	/**
	 * Paketi geçici dosyaya indirir ve özetini doğrular.
	 *
	 * download_url() KULLANMIYORUZ: "güvenli" istek yalnızca 80, 443 ve 8080 portlarına izin verir,
	 * yerel paneli (127.0.0.1:8000) reddeder. Adres zaten panelin İMZALI isteğinden geliyor;
	 * paketin doğruluğunu da adres değil SHA-256 özeti kanıtlıyor.
	 *
	 * @return string|WP_REST_Response geçici dosya yolu ya da hata
	 */
	private static function download( string $url, string $sha256 ) {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return self::error( 400, 'invalid_url', 'Paket adresi geçersiz.' );
		}

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
			return self::error( 400, 'invalid_checksum', 'SHA-256 özeti eksik ya da biçimi yanlış.' );
		}

		$temp     = wp_tempnam( 'ajans-asistani-paket.zip' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'  => 120,
				'stream'   => true,
				'filename' => $temp,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			wp_delete_file( $temp );
			$reason = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );

			return self::error( 502, 'download_failed', "Paket indirilemedi ({$reason}). Adresin süresi dolmuş olabilir." );
		}

		// Bayt bayt aynı dosya mı? Yolda değişen ya da başka bir dosya kurulmaz.
		if ( ! hash_equals( $sha256, hash_file( 'sha256', $temp ) ) ) {
			wp_delete_file( $temp );

			return self::error( 422, 'checksum_mismatch', 'Paketin SHA-256 özeti tutmuyor; dosya kurulmadı.' );
		}

		return $temp;
	}

	/** Upgrader'ın üç farklı başarısızlık biçimini tek mesaja indirir; başarılıysa null */
	private static function failure( $result, WP_Ajax_Upgrader_Skin $skin ): ?string {
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		if ( $skin->get_errors()->has_errors() ) {
			return $skin->get_error_messages();
		}

		return $result ? null : 'İşlem tamamlanamadı (dosya izinlerini kontrol edin).';
	}

	private static function state( string $file ): array {
		$data = get_plugins()[ $file ] ?? array();

		return array(
			'file'    => $file,
			'name'    => $data['Name'] ?? $file,
			'version' => $data['Version'] ?? null,
			'active'  => is_plugin_active( $file ),
		);
	}

	private static function error( int $status, string $code, string $message ): WP_REST_Response {
		return new WP_REST_Response( array( 'error' => $code, 'message' => $message ), $status );
	}
}
