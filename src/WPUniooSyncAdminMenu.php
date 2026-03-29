<?php
namespace LeoKnudsen\WpUniooSync;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists( 'WPUniooSyncAdminMenu' ) ) {
  class WPUniooSyncAdminMenu {
    public function __construct() {
      add_action('admin_menu', [$this, 'add_admin_menu']);
      add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings() {
      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_bearer_token',
        [
          'type'              => 'string',
          'sanitize_callback' => 'sanitize_text_field',
          'default'           => '',
        ]
      );

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_auto_generate_token_on_unauthorization',
        [
          'type'              => 'boolean',
          'sanitize_callback' => function($value) {
            return $value ? true : false;
          },
          'default'           => false,
        ]
      );

      add_settings_section(
        'wp_unioo_sync_api_section',
        __('API Settings', WP_UNIOO_SYNC_TEXTDOMAIN),
        function () {
          echo '<p>' . esc_html__('Enter the API key used for sync requests.', WP_UNIOO_SYNC_TEXTDOMAIN) . '</p>';
        },
        'wp-unioo-sync-settings'
      );

      add_settings_field(
        'wp_unioo_sync_bearer_token',
        __('API Key', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_api_key_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );

      add_settings_field(
        'wp_unioo_sync_auto_generate_token_on_unauthorization',
        __('Auto-Generate API Key on Unauthorized Response', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_auto_generate_token_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );
    }

    public function render_api_key_field() {
      $token = get_option('wp_unioo_sync_bearer_token', '');
      ?>
      <input
        type="password"
        id="wp_unioo_sync_bearer_token"
        name="wp_unioo_sync_bearer_token"
        value="<?php echo esc_attr($token); ?>"
        class="regular-text"
        autocomplete="off"
      />
      <p class="description"><?php esc_html_e('Stored in WordPress options and used when syncing data.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
      <?php
    }

    public function render_auto_generate_token_field() {
      $option_name = 'wp_unioo_sync_auto_generate_token_on_unauthorization';
      $value = get_option($option_name, false);
      ?>
      <label for="<?php echo esc_attr($option_name); ?>">
        <input
          type="checkbox"
          id="<?php echo esc_attr($option_name); ?>"
          name="<?php echo esc_attr($option_name); ?>"
          value="1"
          <?php checked(1, $value, true); ?>
        />
        <?php esc_html_e('Automatically generate a new API key if an unauthorized response is received during sync.', WP_UNIOO_SYNC_TEXTDOMAIN); ?>
      </label>
      <?php
    }

    public function add_admin_menu() {
      add_menu_page(
        __('WP Unioo Sync', WP_UNIOO_SYNC_TEXTDOMAIN),
        __('WP Unioo Sync', WP_UNIOO_SYNC_TEXTDOMAIN),
        'manage_options',
        'wp-unioo-sync',
        [$this, 'admin_page'],
        'dashicons-update',
        6
      );

      // add submenu page for sync logs
      add_submenu_page(
        'wp-unioo-sync',
        __('Sync Logs', WP_UNIOO_SYNC_TEXTDOMAIN),
        __('Sync Logs', WP_UNIOO_SYNC_TEXTDOMAIN),
        'manage_options',
        'wp-unioo-sync-logs',
        [$this, 'admin_page']
      );

      add_submenu_page(
        'wp-unioo-sync',
        __('Settings', WP_UNIOO_SYNC_TEXTDOMAIN),
        __('Settings', WP_UNIOO_SYNC_TEXTDOMAIN),
        'manage_options',
        'wp-unioo-sync-settings',
        [$this, 'admin_settings_page']
      );
    }

    public function admin_page() {
      global $wpdb;

      $sync_logs = $wpdb->get_results(
        "SELECT * FROM " . WP_UNIOO_SYNC_TABLE_NAME .
        " ORDER BY sync_time DESC"
      );

      require_once WP_UNIOO_SYNC_PLUGIN_DIR . 'src/Admin/views/view.sync-logs.php';
    }

    public function admin_settings_page() {
      require_once WP_UNIOO_SYNC_PLUGIN_DIR . 'src/Admin/views/view.sync-settings.php';
    }
  }
}