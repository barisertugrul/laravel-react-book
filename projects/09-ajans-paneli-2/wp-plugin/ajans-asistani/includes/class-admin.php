<?php
/**
 * Ayarlar → Ajans Asistanı: lisans, bağlantı durumu, bağlantı anahtarı üretme, bağlantıyı kesme.
 */

defined( 'ABSPATH' ) || exit;

final class Ajans_Asistani_Admin {

	public static function boot(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu(): void {
		add_options_page( 'Ajans Asistanı', 'Ajans Asistanı', 'manage_options', 'ajans-asistani', array( __CLASS__, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$created       = null;
		$license_error = null;
		$action  = sanitize_key( $_POST['ajans_asistani_action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- aşağıda denetleniyor

		if ( $action ) {
			check_admin_referer( 'ajans_asistani_' . $action ); // CSRF koruması (WordPress nonce'u)

			if ( 'create_key' === $action ) {
				$created = Ajans_Asistani_Keys::create_connection_key();
			} elseif ( 'disconnect' === $action ) {
				Ajans_Asistani_Keys::forget_panel();
			} elseif ( 'license_activate' === $action ) {
				$result        = Ajans_Asistani_License::activate( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
				$license_error = is_wp_error( $result ) ? $result->get_error_message() : null;
			} elseif ( 'license_deactivate' === $action ) {
				Ajans_Asistani_License::deactivate();
			}
		}

		$panel   = Ajans_Asistani_Keys::panel();
		$license = Ajans_Asistani_License::status();
		?>
		<div class="wrap">
			<h1>Ajans Asistanı</h1>

			<h2>Lisans</h2>
			<?php if ( ! empty( $license_error ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $license_error ); ?></p></div>
			<?php endif; ?>

			<?php if ( 'none' === $license['state'] ) : ?>
				<p>Eklenti lisanssız da çalışır; lisans, Ajans Asistanı'nın yeni sürümlerini almak içindir.</p>
				<form method="post">
					<?php wp_nonce_field( 'ajans_asistani_license_activate' ); ?>
					<input type="hidden" name="ajans_asistani_action" value="license_activate">
					<input type="text" name="license_key" class="large-text code" placeholder="ANH1.eyJsaWQi..." autocomplete="off" required>
					<?php submit_button( 'Lisansı etkinleştir', 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<div class="notice <?php echo 'active' === $license['state'] ? 'notice-success' : 'notice-warning'; ?> inline"><p>
					<strong><?php echo 'active' === $license['state'] ? 'Lisans etkin' : 'Lisans geçersiz'; ?>:</strong>
					<?php echo esc_html( trim( ( $license['plan'] ?? '' ) . ' · ' . $license['domain'] . ' · ' . $license['key_hint'] ) ); ?>
					<?php if ( $license['updates_until'] ) : ?>
						· güncellemeler <?php echo esc_html( $license['updates_until'] ); ?> tarihine kadar
					<?php endif; ?>
					<?php if ( $license['error'] ) : ?>
						<br><?php echo esc_html( $license['error'] ); ?>
					<?php endif; ?>
				</p></div>
				<form method="post">
					<?php wp_nonce_field( 'ajans_asistani_license_deactivate' ); ?>
					<input type="hidden" name="ajans_asistani_action" value="license_deactivate">
					<?php submit_button( 'Bu siteden kaldır', 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<hr>

			<h2>Panel bağlantısı</h2>

			<?php if ( $panel ) : ?>
				<div class="notice notice-success inline"><p>
					<strong>Bağlı:</strong> <?php echo esc_html( $panel['agency'] ?: $panel['panel_url'] ); ?>
					· <?php echo esc_html( wp_date( 'j F Y H:i', $panel['paired_at'] ) ); ?>
				</p></div>
				<p>Panel bu siteyi düzenli olarak kontrol ediyor. Her istek, panelin bu siteye özel anahtarıyla imzalıdır.</p>
				<form method="post">
					<?php wp_nonce_field( 'ajans_asistani_disconnect' ); ?>
					<input type="hidden" name="ajans_asistani_action" value="disconnect">
					<?php submit_button( 'Bağlantıyı kes', 'secondary', 'submit', false ); ?>
				</form>
				<hr>
			<?php else : ?>
				<p>Bu site henüz bir panele bağlı değil.</p>
			<?php endif; ?>

			<h2>Bağlantı anahtarı</h2>
			<p>Anahtarı Kumanda panelinde <em>Site ekle</em> sayfasına yapıştırın. Anahtar tek kullanımlıktır ve 15 dakika geçerlidir.</p>

			<?php if ( $created ) : ?>
				<textarea id="ajans-asistani-key" class="large-text code" rows="4" readonly onclick="this.select()"><?php echo esc_textarea( $created['key'] ); ?></textarea>
				<p class="description">
					<?php echo esc_html( 'Geçerlilik: ' . wp_date( 'H:i', $created['expires'] ) . ' saatine kadar. Sayfadan ayrılırsanız anahtar bir daha gösterilmez; gerekirse yenisini oluşturun.' ); ?>
				</p>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'ajans_asistani_create_key' ); ?>
				<input type="hidden" name="ajans_asistani_action" value="create_key">
				<?php submit_button( $created ? 'Yeni anahtar oluştur' : 'Bağlantı anahtarı oluştur', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
