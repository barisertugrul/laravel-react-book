<?php
/**
 * Ayarlar → Ajans Asistanı: bağlantı durumu, bağlantı anahtarı üretme, bağlantıyı kesme.
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

		$created = null;
		$action  = sanitize_key( $_POST['ajans_asistani_action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- aşağıda denetleniyor

		if ( $action ) {
			check_admin_referer( 'ajans_asistani_' . $action ); // CSRF koruması (WordPress nonce'u)

			if ( 'create_key' === $action ) {
				$created = Ajans_Asistani_Keys::create_connection_key();
			} elseif ( 'disconnect' === $action ) {
				Ajans_Asistani_Keys::forget_panel();
			}
		}

		$panel = Ajans_Asistani_Keys::panel();
		?>
		<div class="wrap">
			<h1>Ajans Asistanı</h1>

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
