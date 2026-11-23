<?php
// Eklenti silinirken (yalnızca devre dışı bırakılırken değil) anahtarlar da silinir
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ajans_asistani_keys' );
delete_option( 'ajans_asistani_pairing' );
delete_option( 'ajans_asistani_panel' );
delete_option( 'ajans_asistani_license' ); // sunucudaki yer, devre dışı bırakılırken zaten boşaltıldı
