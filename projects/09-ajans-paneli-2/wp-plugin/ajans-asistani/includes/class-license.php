<?php
/**
 * Ajans Asistanı'nın kendi lisansı: Anahtarcı lisans sunucusu (Bölüm 7).
 *
 *  1. Anahtar (ANH1.<yük>.<imza>) önce ÇEVRİMDIŞI doğrulanır: imza, eklentiye gömülü
 *     açık anahtarla; ürün "ajans-asistani" olmalı. Uydurma anahtar sunucuya hiç gitmez.
 *  2. activate: anahtar + site adresi → siteye özel token (Sanctum)
 *  3. check (günde bir, wp-cron): lisans hâlâ geçerli mi, güncelleme hakkı sürüyor mu?
 *  4. deactivate: eklenti kaldırılırken ya da site taşınırken yer boşaltılır
 *
 * Sunucunun HER yanıtı imzalıdır (X-License-Signature); imzasız ya da bozuk imzalı yanıt
 * kabul edilmez. Böylece hosts dosyasıyla kurulan sahte bir "lisans sunucusu" işe yaramaz.
 */

defined( 'ABSPATH' ) || exit;

final class Ajans_Asistani_License {

	const OPTION     = 'ajans_asistani_license';
	const CRON_HOOK  = 'ajans_asistani_license_check';
	const KEY_PREFIX = 'ANH1';
	const PRODUCT    = 'ajans-asistani';

	/** Satıcının lisans sunucusu. Paylaşımlı hostingte de çalışır: port değil, adres. */
	const DEFAULT_SERVER = 'https://lisans.ornek.com';

	/**
	 * Satıcının açık anahtarı (base64). Gerçek üründe paketleme sırasında buraya yazılır.
	 * Kitaptaki örnekte her okurun Anahtarcı'sı kendi anahtarını üretir (license:keygen);
	 * yerel geliştirmede wp-config.php'deki sabit bunun yerine geçer (bkz. public_key()).
	 */
	const DEFAULT_PUBLIC_KEY = '';

	public static function boot(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'check' ) );
	}

	/** Lisans sunucusunun kök adresi: wp-config.php'de AJANS_ASISTANI_LISANS_SUNUCUSU ile değiştirilebilir */
	public static function server(): string {
		$url = defined( 'AJANS_ASISTANI_LISANS_SUNUCUSU' ) ? (string) AJANS_ASISTANI_LISANS_SUNUCUSU : self::DEFAULT_SERVER;

		return rtrim( $url, '/' ) . '/api/v1/licenses';
	}

	/**
	 * Açık anahtar YALNIZCA yerel geliştirme ortamında wp-config.php'den değiştirilebilir.
	 * Canlı bir sitede bu kapı açık olsaydı, site sahibi kendi anahtar çiftini ve sahte bir
	 * sunucuyu tanımlayıp imzalı "geçerli" yanıtlar üretebilirdi.
	 */
	private static function public_key(): string {
		$key = self::DEFAULT_PUBLIC_KEY;

		if ( 'local' === wp_get_environment_type() && defined( 'AJANS_ASISTANI_LISANS_ACIK_ANAHTAR' ) ) {
			$key = (string) AJANS_ASISTANI_LISANS_ACIK_ANAHTAR;
		}

		return (string) base64_decode( $key, true );
	}

	/** Anahtarın imzası ve ürünü; geçerliyse yük (lid, prd, iat), değilse null */
	public static function parse_key( string $key ): ?array {
		$parts = explode( '.', trim( $key ) );

		if ( 3 !== count( $parts ) || self::KEY_PREFIX !== $parts[0] ) {
			return null;
		}

		$signature  = Ajans_Asistani_Protocol::base64url_decode( $parts[2] );
		$public_key = self::public_key();

		if ( ! $signature || strlen( $signature ) !== SODIUM_CRYPTO_SIGN_BYTES || strlen( $public_key ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
			|| ! sodium_crypto_sign_verify_detached( $signature, $parts[0] . '.' . $parts[1], $public_key ) ) {
			return null;
		}

		$payload = json_decode( (string) Ajans_Asistani_Protocol::base64url_decode( $parts[1] ), true );

		return is_array( $payload ) && self::PRODUCT === ( $payload['prd'] ?? null ) ? $payload : null;
	}

	/** @return array|WP_Error lisansın durumu ya da hata (kod + Türkçe mesaj) */
	public static function activate( string $key ) {
		$key = trim( $key );

		if ( null === self::parse_key( $key ) ) {
			return new WP_Error( 'invalid_key', 'Anahtar geçersiz ya da Ajans Asistanı için değil (imza doğrulanamadı); sunucuya sorulmadı.' );
		}

		$response = self::request( 'POST', '/activate', array( 'key' => $key, 'domain' => home_url() ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		self::save(
			array(
				'key'        => $key,
				'token'      => $response['token'],
				'domain'     => $response['domain'],
				'license'    => $response['license'],
				'checked_at' => time(),
			)
		);

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}

		return self::status();
	}

	/** Günlük kontrol. Sunucuya ulaşılamazsa son bilinen durum korunur (ağ kesintisi lisansı düşürmez). */
	public static function check(): void {
		$stored = self::stored();

		if ( ! $stored ) {
			return;
		}

		$response = self::request( 'GET', '/check', null, $stored['token'] );

		if ( is_wp_error( $response ) ) {
			// 401: token artık yok (lisans portalından bu site kaldırıldı). Ağ hatasında dokunmayız.
			if ( 'unauthenticated' === $response->get_error_code() ) {
				$stored['license']['valid'] = false;
				$stored['error']            = 'Bu site lisanstan kaldırılmış; anahtarı yeniden girin.';
				self::save( $stored );
			}

			return;
		}

		$stored['license']    = $response['license'];
		$stored['checked_at'] = time();
		unset( $stored['error'] );
		self::save( $stored );
	}

	public static function deactivate(): void {
		$stored = self::stored();

		if ( $stored ) {
			self::request( 'POST', '/deactivate', null, $stored['token'] ); // başarısız olsa da yerel kayıt silinir
		}

		delete_option( self::OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Güncelleme hakkı var mı? (Ömür boyu kullanım; güncellemeler süreli.) */
	public static function updates_allowed(): bool {
		$license = self::stored()['license'] ?? array();

		return ! empty( $license['valid'] ) && ! empty( $license['updates_available'] );
	}

	/** Panelin sağlık raporuna giden özet: anahtarın ve token'ın kendisi ASLA gönderilmez */
	public static function status(): array {
		$stored = self::stored();

		if ( ! $stored ) {
			return array( 'state' => 'none' );
		}

		$license = $stored['license'];

		return array(
			'state'             => empty( $license['valid'] ) ? 'invalid' : 'active',
			'plan'              => $license['plan'] ?? null,
			'domain'            => $stored['domain'],
			'max_sites'         => $license['max_sites'] ?? null,
			'sites_used'        => $license['sites_used'] ?? null,
			'updates_until'     => $license['updates_until'] ?? null,
			'updates_available' => ! empty( $license['updates_available'] ),
			'key_hint'          => substr( $stored['key'], 0, 9 ) . '…' . substr( $stored['key'], -6 ),
			'checked_at'        => gmdate( 'c', (int) $stored['checked_at'] ),
			'error'             => $stored['error'] ?? null,
		);
	}

	/**
	 * Lisans sunucusuna istek; imzalı yanıtın gövdesi ya da WP_Error.
	 *
	 * wp_remote_request (wp_safe_remote_* değil): yerel geliştirmede sunucu 127.0.0.1:8001'de;
	 * "güvenli" istek 80/443/8080 dışındaki portları reddeder. Adres yalnızca sabitten gelir.
	 */
	private static function request( string $method, string $path, ?array $body, ?string $token = null ) {
		$headers = array( 'Accept' => 'application/json' );

		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request(
			self::server() . $path,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => null === $body ? null : wp_json_encode( $body ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'unreachable', 'Lisans sunucusuna ulaşılamadı: ' . $response->get_error_message() );
		}

		$raw       = wp_remote_retrieve_body( $response );
		$signature = base64_decode( (string) wp_remote_retrieve_header( $response, 'x-license-signature' ), true );
		$public    = self::public_key();

		// Yanıt gerçekten lisans sunucusundan mı? Başarılı ya da hatalı, her yanıt imzalı olmalı.
		if ( ! $signature || strlen( $signature ) !== SODIUM_CRYPTO_SIGN_BYTES || strlen( $public ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
			|| ! sodium_crypto_sign_verify_detached( $signature, $raw, $public ) ) {
			return new WP_Error( 'bad_signature', 'Lisans sunucusunun yanıtı imzasız ya da imzası geçersiz.' );
		}

		$data   = json_decode( $raw, true );
		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $status ) {
			return new WP_Error( 'unauthenticated', 'Lisans token\'ı geçersiz.' );
		}

		if ( $status >= 400 || ! is_array( $data ) ) {
			return new WP_Error( (string) ( $data['error'] ?? 'license_error' ), (string) ( $data['message'] ?? "Lisans sunucusu HTTP {$status} döndürdü." ) );
		}

		return $data;
	}

	private static function stored(): ?array {
		$value = get_option( self::OPTION );

		return is_array( $value ) && ! empty( $value['token'] ) ? $value : null;
	}

	private static function save( array $value ): void {
		// autoload=false: token her sayfa yüklemesinde belleğe alınmasın
		update_option( self::OPTION, $value, false );
	}
}
