<?php
namespace LeoKnudsen\WpUniooSync;

if ( ! defined('ABSPATH') ) {
  exit();
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_username',
        [
          'type' => 'string',
          'sanitize_callback' => 'sanitize_text_field',
          'default' => ''
        ]
      );

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_password',
        [
          'type' => 'string',
          'sanitize_callback' => 'sanitize_text_field',
          'default' => ''
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

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_members_table',
        [
          'type' => 'boolean',
          'sanitize_callback' => function($value) {
            return $value ? true : false;
          },
          'default' => false,
        ]
      );

      // register required membership setting to only sync members with an active membership in Unioo
      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_required_membership',
        [
          'type' => 'boolean',
          'sanitize_callback' => function($value) {
            return $value ? true : false;
          },
          'default' => false,
        ]
      );

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_user_default_username_field',
        [
          'type' => 'string',
          'sanitize_callback' => 'sanitize_text_field',
          'default' => ''
        ]
      );

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_user_default_password_field',
        [
          'type' => 'string',
          'sanitize_callback' => 'sanitize_text_field',
          'default' => 'generate_random'
        ]
      );

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_default_email_address_on_sync',
        [
          'type' => 'string',
          'sanitize_callback' => 'sanitize_email',
          'default' => ''
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
        'wp_unioo_sync_username',
        __('Unioo username', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_username_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );

      add_settings_field(
        'wp_unioo_sync_password',
        __('Unioo password', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_password_field'],
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

      add_settings_field(
        'wp_unioo_sync_members_table',
        __('Use Custom Members Table', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_custom_members_table_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );

      add_settings_field(
        'wp_unioo_sync_required_membership',
        __('Required Membership', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_required_membership_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );

      add_settings_field(
        'wp_unioo_sync_user_default_username_field',
        __('Default Username Field', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_default_username_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );

      add_settings_field(
        'wp_unioo_sync_user_default_password_field',
        __('Default User Password Field', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_default_user_password_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
      );

      add_settings_field(
        'wp_unioo_sync_default_email_address_on_sync',
        __('Default Email Address on Sync', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_default_email_address_on_sync_field'],
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

    public function render_username_field() {
      $username = get_option('wp_unioo_sync_username', '');
      ?>
      <input
        type="text"
        id="wp_unioo_sync_username"
        name="wp_unioo_sync_username"
        value="<?php echo esc_attr($username); ?>"
        class="regular-text"
      />
      <?php
    }

    public function render_password_field() {
      $password = get_option('wp_unioo_sync_password', '');
      ?>
      <input
        type="password"
        id="wp_unioo_sync_password"
        name="wp_unioo_sync_password"
        value="<?php echo esc_attr($password); ?>"
        class="regular-text"
        autocomplete="off"
      />
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

    public function render_custom_members_table_field() {
      global $wpdb;
      $option_name = 'wp_unioo_sync_members_table';
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
        <p><?php esc_html_e('Store synced members in a custom database table instead of user meta. This is useful for large member lists or if you want to keep the data separate from WordPress users.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
        <p>
          <?php
          printf(
            /* translators: %s: database table name */
            esc_html__('Note: If enabled, the plugin will create following table %s in the WordPress database to store member data. Make sure to run the sync process after enabling this option to populate the table with member data.', WP_UNIOO_SYNC_TEXTDOMAIN),
            esc_html($wpdb->prefix . 'unioo_members')
          );
          ?>
        </p>
      </label>
      <?php
    }

    public function render_required_membership_field() {
      $option_name = 'wp_unioo_sync_required_membership';
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
        <?php esc_html_e('Only syncs and creates users for members with an active membership in Unioo.', WP_UNIOO_SYNC_TEXTDOMAIN); ?>
      </label>
      <?php
    }

    public function render_default_username_field() {
      $option_name = 'wp_unioo_sync_user_default_username_field';
      $value = get_option($option_name, '');
      ?>
      <input
        type="text"
        id="<?php echo esc_attr($option_name); ?>"
        name="<?php echo esc_attr($option_name); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text"
      />
      <p class="description"><?php esc_html_e('Specify the default field from the Unioo member data to use as the WordPress username when creating users during sync.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
      <p class="description"><?php esc_html_e('Add the field as {{field_name}}', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
      <?php
    }

    public function render_default_user_password_field() {
      $option_name = 'wp_unioo_sync_user_default_password_field';
      $value = get_option($option_name, 'generate_random');
      ?>
      <input
        type="text"
        id="<?php echo esc_attr($option_name); ?>"
        name="<?php echo esc_attr($option_name); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text"
      />
      <p class="description"><?php esc_html_e('Specify the default password to assign to users created during sync. Default is "generate_random", which will create a random password for each user.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
      <p class="description"><?php esc_html_e('You can also specify a fixed password or use a field from the Unioo member data by adding it as {{field_name}}', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
      <?php
    }

    public function render_default_email_address_on_sync_field() {
      $option_name = 'wp_unioo_sync_default_email_address_on_sync';
      $value = get_option($option_name, '');
      ?>
      <input
        type="email"
        id="<?php echo esc_attr($option_name); ?>"
        name="<?php echo esc_attr($option_name); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text"
      />
      <p class="description"><?php esc_html_e('Specify a default email address to notify when a synchronization is complete. "log" will create a log file (this is useful for debugging purposes).', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
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
        " ORDER BY sync_time DESC",
        OBJECT
      );

      if( defined('WP_UNIOO_SYNC_PLUGIN_DIR')) {
        require_once WP_UNIOO_SYNC_PLUGIN_DIR . 'src/Admin/views/view.sync-logs.php';
      } else {
        require_once plugin_dir_path(__FILE__) . 'views/view.sync-logs.php';
      }
    }

    public function admin_settings_page() {
      if (defined('WP_UNIOO_SYNC_PLUGIN_DIR')) {
        require_once WP_UNIOO_SYNC_PLUGIN_DIR . 'src/Admin/views/view.sync-settings.php';
      } else {
        require_once plugin_dir_path(__FILE__) . 'views/view.sync-settings.php';
      }
    }
  }
}