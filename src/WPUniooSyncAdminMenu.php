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
        'wp_unioo_sync_defaul_database_table',
        [
          'type' => 'string',
          'sanitize_callback' => function($value) {
            global $wpdb;
            $table_name = $wpdb->prefix . sanitize_key($value);
            if ( ! empty($value) && $wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name ) {
              // Table does not exist, create it
              $charset_collate = $wpdb->get_charset_collate();
              $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                member_id varchar(50) NOT NULL,
                member_data longtext NOT NULL,
                sync_time datetime NOT NULL,
                PRIMARY KEY  (id)
              ) $charset_collate;";

              dbDelta($sql);
            }
            return sanitize_text_field($value);
          },
          'default' => ''
        ]
      );

      register_setting(
        'wp_unioo_sync_settings_group',
        'wp_unioo_sync_database_table_fields',
        [
          'type' => 'array',
          'sanitize_callback' => function($value) {
            global $wpdb;

            $option_table = sanitize_key(get_option('wp_unioo_sync_defaul_database_table', ''));

            if (empty($option_table) || ! is_array($value)) {
              return [];
            }

            $table_name = $wpdb->prefix . $option_table;
            $existing_option = get_option('wp_unioo_sync_database_table_fields', []);
            if (! is_array($existing_option)) {
              $existing_option = [];
            }

            $allowed_types = [
              'VARCHAR(255)',
              'TEXT',
              'LONGTEXT',
              'INT',
              'BIGINT',
              'DATETIME',
              'DATE',
              'TINYINT(1)',
            ];

            $reserved_columns = [
              'id',
              'member_id',
              'member_data',
              'sync_time',
            ];

            $normalized_fields = [];
            foreach ($value as $row) {
              if (! is_array($row)) {
                continue;
              }

              $column_name = sanitize_key(isset($row['name']) ? $row['name'] : '');
              $column_type = strtoupper(sanitize_text_field(isset($row['type']) ? $row['type'] : 'VARCHAR(255)'));

              if (empty($column_name) || in_array($column_name, $reserved_columns, true)) {
                continue;
              }

              if (! in_array($column_type, $allowed_types, true)) {
                $column_type = 'VARCHAR(255)';
              }

              $normalized_fields[$column_name] = [
                'name' => $column_name,
                'type' => $column_type,
              ];
            }

            $normalized_fields = array_values($normalized_fields);

            $existing_names = [];
            foreach ($existing_option as $existing) {
              if (! is_array($existing)) {
                continue;
              }

              $existing_name = sanitize_key(isset($existing['name']) ? $existing['name'] : '');
              if (! empty($existing_name)) {
                $existing_names[] = $existing_name;
              }
            }

            $new_names = array_map(
              function($field) {
                return $field['name'];
              },
              $normalized_fields
            );

            $columns_to_drop = array_diff($existing_names, $new_names);

            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name) {
              foreach ($normalized_fields as $field) {
                $has_column = $wpdb->get_var(
                  $wpdb->prepare(
                    'SHOW COLUMNS FROM `' . esc_sql($table_name) . '` LIKE %s',
                    $field['name']
                  )
                );

                if (! $has_column) {
                  $wpdb->query(
                    'ALTER TABLE `' . esc_sql($table_name) . '` ADD COLUMN `' . esc_sql($field['name']) . '` ' . $field['type'] . ' NULL'
                  );
                }
              }

              foreach ($columns_to_drop as $column_name) {
                if (in_array($column_name, $reserved_columns, true)) {
                  continue;
                }

                $has_column = $wpdb->get_var(
                  $wpdb->prepare(
                    'SHOW COLUMNS FROM `' . esc_sql($table_name) . '` LIKE %s',
                    $column_name
                  )
                );

                if ($has_column) {
                  $wpdb->query(
                    'ALTER TABLE `' . esc_sql($table_name) . '` DROP COLUMN `' . esc_sql($column_name) . '`'
                  );
                }
              }
            }

            return $normalized_fields;
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
        'wp_unioo_sync_defaul_database_table',
        __('Default Database Table', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_default_database_table_field'],
        'wp-unioo-sync-settings',
        'wp_unioo_sync_api_section'
       );

       add_settings_field(
        'wp_unioo_sync_database_table_fields',
        __('Database Table Fields', WP_UNIOO_SYNC_TEXTDOMAIN),
        [$this, 'render_database_table_fields_field'],
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

    public function render_default_database_table_field() {
      global $wpdb;
      $option_name = 'wp_unioo_sync_defaul_database_table';
      $value = get_option($option_name, '');
      ?>
      <?php echo $wpdb->prefix;?>
      <input
        type="text"
        id="<?php echo esc_attr($option_name); ?>"
        name="<?php echo esc_attr($option_name); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text"
      />
      <p class="description"><?php esc_html_e('Specify a custom database table for storing synced member data. If left empty, member data will be stored in user meta.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>

      <?php
    }

    public function render_database_table_fields_field() {
      $option_name = 'wp_unioo_sync_database_table_fields';
      $value = get_option($option_name, []);

      if (! is_array($value)) {
        $value = [];
      }

      $types = [
        'VARCHAR(255)',
        'TEXT',
        'LONGTEXT',
        'INT',
        'BIGINT',
        'DATETIME',
        'DATE',
        'TINYINT(1)',
      ];
      ?>

      <table class="widefat striped" id="wp-unioo-db-fields-table" style="max-width: 900px; margin-bottom: 10px;">
        <thead>
          <tr>
            <th><?php esc_html_e('Column Name', WP_UNIOO_SYNC_TEXTDOMAIN); ?></th>
            <th><?php esc_html_e('Column Type', WP_UNIOO_SYNC_TEXTDOMAIN); ?></th>
            <th><?php esc_html_e('Actions', WP_UNIOO_SYNC_TEXTDOMAIN); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (! empty($value)) : ?>
            <?php foreach ($value as $index => $field) : ?>
              <?php
              $field_name = sanitize_key(isset($field['name']) ? $field['name'] : '');
              $field_type = strtoupper(sanitize_text_field(isset($field['type']) ? $field['type'] : 'VARCHAR(255)'));
              if (! in_array($field_type, $types, true)) {
                $field_type = 'VARCHAR(255)';
              }
              ?>
              <tr>
                <td>
                  <input
                    type="text"
                    name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($index); ?>][name]"
                    value="<?php echo esc_attr($field_name); ?>"
                    placeholder="custom_column"
                    class="regular-text"
                    pattern="[a-zA-Z0-9_]+"
                  />
                </td>
                <td>
                  <select name="<?php echo esc_attr($option_name); ?>[<?php echo esc_attr($index); ?>][type]">
                    <?php foreach ($types as $type) : ?>
                      <option value="<?php echo esc_attr($type); ?>" <?php selected($field_type, $type); ?>>
                        <?php echo esc_html($type); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <button type="button" class="button wp-unioo-remove-db-field"><?php esc_html_e('Remove', WP_UNIOO_SYNC_TEXTDOMAIN); ?></button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <button type="button" class="button" id="wp-unioo-add-db-field"><?php esc_html_e('Add Field', WP_UNIOO_SYNC_TEXTDOMAIN); ?></button>
      <p class="description">
        <?php esc_html_e('Add or remove custom columns for the configured database table. Saving this form will update the table columns.', WP_UNIOO_SYNC_TEXTDOMAIN); ?>
      </p>
      <p class="description">
        <?php esc_html_e('Reserved columns (id, member_id, member_data, sync_time) cannot be changed here.', WP_UNIOO_SYNC_TEXTDOMAIN); ?>
      </p>

      <script>
        (function() {
          const tableBody = document.querySelector('#wp-unioo-db-fields-table tbody');
          const addButton = document.getElementById('wp-unioo-add-db-field');
          if (!tableBody || !addButton) {
            return;
          }

          const optionName = <?php echo wp_json_encode($option_name); ?>;
          const fieldTypes = <?php echo wp_json_encode($types); ?>;

          const getNextIndex = function() {
            const rows = tableBody.querySelectorAll('tr');
            return rows.length;
          };

          const createTypeOptions = function(selectedType) {
            return fieldTypes.map(function(type) {
              const selected = type === selectedType ? ' selected' : '';
              return '<option value="' + type + '"' + selected + '>' + type + '</option>';
            }).join('');
          };

          const bindRemoveButtons = function() {
            tableBody.querySelectorAll('.wp-unioo-remove-db-field').forEach(function(button) {
              button.onclick = function() {
                const row = button.closest('tr');
                if (row) {
                  row.remove();
                }
              };
            });
          };

          addButton.addEventListener('click', function() {
            const index = getNextIndex();
            const row = document.createElement('tr');

            row.innerHTML =
              '<td>' +
                '<input type="text" class="regular-text" pattern="[a-zA-Z0-9_]+" placeholder="custom_column" name="' + optionName + '[' + index + '][name]" />' +
              '</td>' +
              '<td>' +
                '<select name="' + optionName + '[' + index + '][type]">' + createTypeOptions('VARCHAR(255)') + '</select>' +
              '</td>' +
              '<td>' +
                '<button type="button" class="button wp-unioo-remove-db-field"><?php echo esc_js(__('Remove', WP_UNIOO_SYNC_TEXTDOMAIN)); ?></button>' +
              '</td>';

            tableBody.appendChild(row);
            bindRemoveButtons();
          });

          bindRemoveButtons();
        })();
      </script>
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