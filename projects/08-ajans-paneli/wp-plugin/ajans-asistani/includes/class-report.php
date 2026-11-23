<?php
/**
 * Panelin istediği sağlık raporu. Yalnızca okur; hiçbir ayarı değiştirmez.
 */

defined( 'ABSPATH' ) || exit;

final class Ajans_Asistani_Report {

	public static function build(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		return array(
			'site_name'          => get_bloginfo( 'name' ),
			'wp_version'         => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'agent_version'      => AJANS_ASISTANI_VERSION,
			'core_update'        => self::core_update(),
			'plugins'            => self::plugins(),
			'themes'             => self::themes(),
			'debug'              => defined( 'WP_DEBUG' ) && WP_DEBUG,
			// WP_DEBUG_DISPLAY tanımlı değilse varsayılanı true'dur
			'debug_display'      => defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ),
			'file_edit_disabled' => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'admin_count'        => count( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) ),
		);
	}

	/** WordPress'in kendi güncelleme denetiminin sonucu (wp-cron günde iki kez yeniler) */
	private static function core_update(): ?string {
		$core = get_site_transient( 'update_core' );

		foreach ( $core->updates ?? array() as $offer ) {
			if ( 'upgrade' === ( $offer->response ?? '' ) ) {
				return $offer->current;
			}
		}

		return null;
	}

	private static function plugins(): array {
		$updates = get_site_transient( 'update_plugins' )->response ?? array();
		$list    = array();

		foreach ( get_plugins() as $file => $data ) {
			$list[] = array(
				'file'    => $file,
				'name'    => $data['Name'],
				'version' => $data['Version'],
				'active'  => is_plugin_active( $file ),
				'update'  => isset( $updates[ $file ] ) ? $updates[ $file ]->new_version : null,
			);
		}

		return $list;
	}

	private static function themes(): array {
		$updates = get_site_transient( 'update_themes' )->response ?? array();
		$active  = get_stylesheet();
		$list    = array();

		foreach ( wp_get_themes() as $slug => $theme ) {
			$list[] = array(
				'slug'    => $slug,
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'active'  => $slug === $active,
				'update'  => $updates[ $slug ]['new_version'] ?? null,
			);
		}

		return $list;
	}
}
