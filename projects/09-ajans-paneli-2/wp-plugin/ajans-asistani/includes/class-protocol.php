<?php
/**
 * Panel ile AYNI protokol (AA1). Panel tarafı: app/Agent/AgentProtocol.php
 */

defined( 'ABSPATH' ) || exit;

final class Ajans_Asistani_Protocol {

	const VERSION        = 'AA1';
	const MAX_CLOCK_SKEW = 300; // saniye
	const NONCE_TTL      = 600; // nonce, saat penceresinden uzun saklanır: pencere içinde tekrar imkânsız

	public static function request_message( string $method, string $route, string $timestamp, string $nonce, string $body ): string {
		return implode(
			"\n",
			array(
				self::VERSION . '-REQ',
				strtoupper( $method ),
				'/' . ltrim( $route, '/' ),
				$timestamp,
				$nonce,
				hash( 'sha256', $body ),
			)
		);
	}

	public static function response_message( string $request_nonce, int $status, string $timestamp, string $body ): string {
		return implode(
			"\n",
			array(
				self::VERSION . '-RES',
				$request_nonce,
				(string) $status,
				$timestamp,
				hash( 'sha256', $body ),
			)
		);
	}

	public static function base64url_encode( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	public static function base64url_decode( string $text ): ?string {
		if ( ! preg_match( '/^[A-Za-z0-9_-]*$/', $text ) ) {
			return null;
		}
		$decoded = base64_decode( strtr( $text, '-_', '+/' ), true );

		return false === $decoded ? null : $decoded;
	}
}
