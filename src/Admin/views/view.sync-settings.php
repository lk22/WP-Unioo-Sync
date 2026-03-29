<?php
if ( ! defined('ABSPATH') ) {
	exit();
}
?>

<div class="wrap">
	<h1><?php esc_html_e('WP Unioo Sync Settings', WP_UNIOO_SYNC_TEXTDOMAIN); ?></h1>

	<form method="post" action="options.php">
		<?php
      settings_fields('wp_unioo_sync_settings_group');
      do_settings_sections('wp-unioo-sync-settings');
      submit_button(__('Save Options', WP_UNIOO_SYNC_TEXTDOMAIN));
		?>
	</form>
</div>
