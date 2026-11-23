<?php
/**
 * Anahtarlar ve eşleştirme durumu (wp_options).
 *
 *  ajans_asistani_keys     eklentinin anahtar çifti (gizli anahtar bu sitede kalır)
 *  ajans_asistani_pairing  tek kullanımlık jetonun ÖZETİ + bitiş zamanı
 *  ajans_asistani_panel    eşleşilen panelin açık anahtarı ve bilgileri
 */

defined( 'ABSPATH' ) || exit;

final class Ajans_Asistani_Keys {

	const PAIRING_TTL = 15 * MINUTE_IN_SECONDS;

	public static function ensure_keypair(): void {
		if ( get_option( 'ajans_asistani_keys' ) ) {
			return;
		}

		$pair = sodium_crypto_sign_keypair();

		// autoload=false: gizli anahtar her sayfa yüklemesinde belleğe alınmasın
		add_option(
			'ajans_asistani_keys',
			array(
				'secret' => base64_encode( sodium_crypto_sign_secretkey( $pair ) ),
				'public' => base64_encode( sodium_crypto_sign_publickey( $pair ) ),
			),
			'',
			false
		);
	}

	public static function secret_key(): string {
		self::ensure_keypair();

		return base64_decode( get_option( 'ajans_asistani_keys' )['secret'] );
	}

	public static function public_key_b64(): string {
		self::ensure_keypair();

		return get_option( 'ajans_asistani_keys' )['public'];
	}

	/**
	 * Yeni bağlantı anahtarı: "AA1." + base64url(JSON).
	 * Jetonun kendisi saklanmaz, yalnızca özeti; anahtar bir kez gösterilir.
	 */
	public static function create_connection_key(): array {
		$token   = bin2hex( random_bytes( 24 ) );
		$expires = time() + self::PAIRING_TTL;

		update_option(
			'ajans_asistani_pairing',
			array(
				'token_hash' => hash( 'sha256', $token ),
				'expires'    => $expires,
			),
			false
		);

		$payload = wp_json_encode(
			array(
				'u' => home_url(),
				'a' => get_rest_url(),
				't' => $token,
				'k' => self::public_key_b64(),
			),
			JSON_UNESCAPED_SLASHES
		);

		return array(
			'key'     => Ajans_Asistani_Protocol::VERSION . '.' . Ajans_Asistani_Protocol::base64url_encode( $payload ),
			'expires' => $expires,
		);
	}

	/** Jeton doğruysa TÜKETİR (tek kullanımlık) ve true döner */
	public static function consume_pairing_token( string $token ): bool {
		$pairing = get_option( 'ajans_asistani_pairing' );

		if ( ! is_array( $pairing ) || $pairing['expires'] < time() ) {
			return false;
		}

		// hash_equals: karşılaştırma süresi, eşleşen karakter sayısını ele vermez
		if ( ! hash_equals( $pairing['token_hash'], hash( 'sha256', $token ) ) ) {
			return false;
		}

		delete_option( 'ajans_asistani_pairing' );

		return true;
	}

	public static function panel(): ?array {
		$panel = get_option( 'ajans_asistani_panel' );

		return is_array( $panel ) ? $panel : null;
	}

	public static function save_panel( array $panel ): void {
		update_option( 'ajans_asistani_panel', $panel, false );
	}

	public static function forget_panel(): void {
		delete_option( 'ajans_asistani_panel' );
	}
}
