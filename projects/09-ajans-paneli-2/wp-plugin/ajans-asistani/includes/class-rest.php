<?php
/**
 * REST uçları:  POST /ajans-asistani/v1/pair             (jeton + panelin açık anahtarı)
 *               GET  /ajans-asistani/v1/health           (imzalı istek → sağlık raporu)
 *               POST /ajans-asistani/v1/plugins/install  (imzalı komut → eklenti kur)
 *               POST /ajans-asistani/v1/plugins/update   (imzalı komut → eklenti güncelle)
 *               POST /ajans-asistani/v1/license/activate (imzalı komut → lisansı etkinleştir)
 *               POST /ajans-asistani/v1/license/deactivate
 *
 * Gelen her istek: imza + zaman damgası (±5 dk) + nonce (bir kez) doğrulanır.
 * Giden her yanıt: eklentinin gizli anahtarıyla, İSTEĞİN nonce'una bağlı olarak imzalanır.
 */

defined( 'ABSPATH' ) || exit;

final class Ajans_Asistani_Rest {

	const NAMESPACE = 'ajans-asistani/v1';

	public static function boot(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		// Öncelik 100: WordPress'in kendi başlıkları (CORS vb., öncelik 10) önce gönderilsin; gövdeyi en son biz yazarız
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'sign_response' ), 100, 4 );
	}

	public static function routes(): void {
		// permission_callback herkese açık: kimlik WordPress oturumuyla değil İMZAYLA doğrulanır
		register_rest_route(
			self::NAMESPACE,
			'/pair',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'pair' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'health' ),
				'permission_callback' => '__return_true',
			)
		);

		// Komutlar: hepsi aynı kapıdan (command) geçer, imza doğrulanmadan hiçbiri çalışmaz
		$commands = array(
			'/plugins/install'    => array( 'Ajans_Asistani_Commands', 'install' ),
			'/plugins/update'     => array( 'Ajans_Asistani_Commands', 'update' ),
			'/license/activate'   => array( 'Ajans_Asistani_Commands', 'activate_license' ),
			'/license/deactivate' => array( 'Ajans_Asistani_Commands', 'deactivate_license' ),
		);

		foreach ( $commands as $route => $handler ) {
			register_rest_route(
				self::NAMESPACE,
				$route,
				array(
					'methods'             => 'POST',
					'callback'            => fn ( WP_REST_Request $request ) => self::command( $request, $handler ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	public static function pair( WP_REST_Request $request ): WP_REST_Response {
		$data = json_decode( $request->get_body(), true );

		if ( ! is_array( $data ) || empty( $data['token'] ) || empty( $data['panel_public_key'] ) || empty( $data['site_id'] ) ) {
			return self::error( 400, 'invalid_request', 'Eksik eşleştirme bilgisi.' );
		}

		$panel_key = base64_decode( (string) $data['panel_public_key'], true );

		// Panel, isteği göndereceği açık anahtarın gizli eşiyle imzalamış olmalı (anahtar sahipliği kanıtı)
		$error = self::verify( $request, $panel_key ?: '', (string) $data['site_id'] );
		if ( $error ) {
			return $error;
		}

		// İmza tek başına yetmez: herkes bir anahtar çifti üretip imzalayabilir.
		// Yetkiyi, yöneticinin bu sitede ürettiği tek kullanımlık jeton verir.
		if ( ! Ajans_Asistani_Keys::consume_pairing_token( (string) $data['token'] ) ) {
			return self::error( 403, 'invalid_token', 'Bağlantı anahtarı geçersiz, kullanılmış ya da süresi dolmuş. Eklentiden yeni bir anahtar oluşturun.' );
		}

		Ajans_Asistani_Keys::save_panel(
			array(
				'site_id'    => (string) $data['site_id'],
				'public_key' => base64_encode( $panel_key ),
				'panel_url'  => esc_url_raw( (string) ( $data['panel_url'] ?? '' ) ),
				'agency'     => sanitize_text_field( (string) ( $data['agency'] ?? '' ) ),
				'paired_at'  => time(),
			)
		);

		return new WP_REST_Response( array( 'paired' => true, 'site_name' => get_bloginfo( 'name' ) ), 200 );
	}

	public static function health( WP_REST_Request $request ): WP_REST_Response {
		$panel = Ajans_Asistani_Keys::panel();

		if ( ! $panel ) {
			return self::error( 409, 'not_paired', 'Bu site bir panele bağlı değil.' );
		}

		$error = self::verify( $request, base64_decode( $panel['public_key'] ), $panel['site_id'] );
		if ( $error ) {
			return $error;
		}

		return new WP_REST_Response( Ajans_Asistani_Report::build(), 200 );
	}

	/** İmzalı komut: önce panel doğrulanır, sonra gövde (JSON) komuta verilir */
	private static function command( WP_REST_Request $request, callable $handler ): WP_REST_Response {
		$panel = Ajans_Asistani_Keys::panel();

		if ( ! $panel ) {
			return self::error( 409, 'not_paired', 'Bu site bir panele bağlı değil.' );
		}

		$error = self::verify( $request, base64_decode( $panel['public_key'] ), $panel['site_id'] );
		if ( $error ) {
			return $error;
		}

		$data = json_decode( $request->get_body(), true );

		return $handler( is_array( $data ) ? $data : array() );
	}

	/** Doğrulama başarılıysa null, değilse imzalı bir hata yanıtı */
	private static function verify( WP_REST_Request $request, string $public_key, string $expected_site ): ?WP_REST_Response {
		$timestamp = (string) $request->get_header( 'X-Ajans-Timestamp' );
		$nonce     = (string) $request->get_header( 'X-Ajans-Nonce' );
		$site      = (string) $request->get_header( 'X-Ajans-Site' );
		$signature = Ajans_Asistani_Protocol::base64url_decode( (string) $request->get_header( 'X-Ajans-Signature' ) );

		if ( ! ctype_digit( $timestamp ) || ! preg_match( '/^[a-f0-9]{32}$/', $nonce ) || ! $signature ) {
			return self::error( 401, 'unsigned', 'İmza başlıkları eksik.' );
		}

		if ( abs( time() - (int) $timestamp ) > Ajans_Asistani_Protocol::MAX_CLOCK_SKEW ) {
			return self::error( 401, 'stale', 'İsteğin zaman damgası geçersiz (sunucu saatlerini kontrol edin).' );
		}

		if ( ! hash_equals( $expected_site, $site ) ) {
			return self::error( 401, 'wrong_site', 'İstek başka bir site için.' );
		}

		$message = Ajans_Asistani_Protocol::request_message( $request->get_method(), $request->get_route(), $timestamp, $nonce, $request->get_body() );

		if ( strlen( $public_key ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen( $signature ) !== SODIUM_CRYPTO_SIGN_BYTES
			|| ! sodium_crypto_sign_verify_detached( $signature, $message, $public_key ) ) {
			return self::error( 401, 'bad_signature', 'İmza doğrulanamadı.' );
		}

		// Nonce'u imzayı doğruladıktan SONRA kaydederiz: imzasız istekler depoyu dolduramaz.
		// Aynı imzalı istek ikinci kez gelirse (tekrar saldırısı) burada durur.
		$key = 'ajans_asistani_nonce_' . $nonce;
		if ( get_transient( $key ) ) {
			return self::error( 401, 'replay', 'Bu istek daha önce kullanıldı.' );
		}
		set_transient( $key, 1, Ajans_Asistani_Protocol::NONCE_TTL );

		return null;
	}

	private static function error( int $status, string $code, string $message ): WP_REST_Response {
		return new WP_REST_Response( array( 'error' => $code, 'message' => $message ), $status );
	}

	/**
	 * Yanıtı WordPress yerine biz yazarız: imzalanan gövde ile gönderilen gövde bayt bayt aynı olmalı.
	 *
	 * @param bool             $served  başka bir filtre yanıtı zaten gönderdi mi
	 * @param WP_HTTP_Response $result
	 */
	public static function sign_response( $served, $result, WP_REST_Request $request, WP_REST_Server $server ) {
		if ( $served || ! str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/' ) ) {
			return $served;
		}

		$body      = wp_json_encode( $server->response_to_data( $result, false ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$timestamp = (string) time();
		$nonce     = (string) $request->get_header( 'X-Ajans-Nonce' );
		$message   = Ajans_Asistani_Protocol::response_message( $nonce, $result->get_status(), $timestamp, $body );
		$signature = sodium_crypto_sign_detached( $message, Ajans_Asistani_Keys::secret_key() );

		$server->send_header( 'X-Ajans-Timestamp', $timestamp );
		$server->send_header( 'X-Ajans-Signature', Ajans_Asistani_Protocol::base64url_encode( $signature ) );
		$server->send_header( 'Cache-Control', 'no-store' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON gövdesi

		return true;
	}
}
