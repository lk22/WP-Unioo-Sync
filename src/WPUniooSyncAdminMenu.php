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

    // Register settings for API key and GraphQL endpoint URL
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

      // Register setting for GraphQL endpoint URL
      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_graphql_url',
        [
          'type' => 'string',
          'sanitize_callback' => 'esc_url_raw',
          'default' => ''
        ]
      );

      // Register setting for auto-generating API key on unauthorized response
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

      // Register custom fields setting for future extensibility
      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_custom_fields',
        [
          'type' => 'array',
          'sanitize_callback' => function($value) {
            if (is_string($value)) {
              $decoded = json_decode($value, true);
              if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
              } else {
                add_settings_error(
                  'wp_unioo_sync_custom_fields',
                  'invalid_json',
                  __('Invalid JSON format for custom fields.', WP_UNIOO_SYNC_TEXTDOMAIN),
                  'error'
                );
                return [];
              }
            }
          },
          'default' => [],
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
        'wp_unioo_sync_graphql_url',
        __('GraphQL Endpoint URL', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_api_url'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
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

      add_settings_field(
        'wp_unioo_sync_custom_fields',
        __('Custom Fields', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_custom_fields_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );
    }

    public function render_api_url() {
      $url = get_option('wp_unioo_sync_graphql_url', '');
      ?>
      <input
        type="text"
        id="wp_unioo_sync_graphql_url"
        name="wp_unioo_sync_graphql_url"
        value="<?php echo esc_attr($url); ?>"
        class="regular-text"
      />
      <p class="description"><?php esc_html_e('The GraphQL endpoint URL for Unioo API.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
      <?php
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

    public function render_custom_fields_field() {
      $custom_fields = get_option('wp_unioo_sync_custom_fields', []);
      ?>
      <textarea
        id="wp_unioo_sync_custom_fields"
        name="wp_unioo_sync_custom_fields"
        rows="5"
        cols="50"
        class="large-text code"
      ><?php echo esc_textarea(json_encode($custom_fields, JSON_PRETTY_PRINT)); ?></textarea>
      <p class="description">
        <?php esc_html_e('This allows you to specify additional fields that should be included in the sync process. it will be supported for JSON and CSV imports', WP_UNIOO_SYNC_TEXTDOMAIN); ?>
      </p>
      <p class="description">
        <?php esc_html_e('Enter the custom fields in JSON format. Example: {"field_name": "Field Label", "another_field": "Another Field Label"}', WP_UNIOO_SYNC_TEXTDOMAIN); ?>
      </p>
      <p class="error">
        <?php settings_errors('wp_unioo_sync_custom_fields'); ?>
      </p>
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
        "SELECT * FROM " . "wp_unioo_sync" .
        " ORDER BY sync_time DESC",
        OBJECT
      );

      require_once WP_UNIOO_SYNC_PLUGIN_DIR . 'src/Admin/views/view.sync-logs.php';
    }

    public function admin_settings_page() {
      require_once WP_UNIOO_SYNC_PLUGIN_DIR . 'src/Admin/views/view.sync-settings.php';
    }
  }
}