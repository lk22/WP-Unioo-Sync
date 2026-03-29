<div class="wrap">
  <h1><?php echo __('WP Unioo Sync', WP_UNIOO_SYNC_TEXTDOMAIN); ?></h1>
  <p><?php echo __('Welcome_to the WP Unioo Sync plugin, use this page to manage your sync settings and view sync logs', WP_UNIOO_SYNC_TEXTDOMAIN)?></p>
  <h2><?php echo __('Sync Logs', WP_UNIOO_SYNC_TEXTDOMAIN); ?></h2>
  <table class="wp-list-table widefat fixed striped">
    <thead>
      <tr>
        <th><?php echo __('ID', WP_UNIOO_SYNC_TEXTDOMAIN); ?></th>
        <th><?php echo __('Status', WP_UNIOO_SYNC_TEXTDOMAIN); ?></th>
        <th><?php echo __('Time', WP_UNIOO_SYNC_TEXTDOMAIN); ?></th>
        <th><?php echo __('Message', WP_UNIOO_SYNC_TEXTDOMAIN); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ( ! empty($sync_logs) ) : ?>
        <?php foreach ( $sync_logs as $log ) : ?>
          <tr>
            <td><?php echo esc_html( $log->id ); ?></td>
            <td><?php echo esc_html( $log->status ); ?></td>
            <td><?php echo esc_html( $log->sync_time ); ?></td>
            <td><?php echo esc_html( $log->message ); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
  const syncLogsTable = document.querySelector('.wp-list-table');
  if (syncLogsTable) {
    syncLogsTable.addEventListener('click', function(e) {
      if (e.target.tagName === 'TD') {
        const logId = e.target.parentElement.querySelector('td:first-child').textContent;
        alert('Log ID: ' + logId);
      }
    });
  }

  const bearerToken = <?php echo json_encode(get_option('wp_unioo_sync_bearer_token')); ?>;
  console.log('Bearer Token:', bearerToken);
</script>