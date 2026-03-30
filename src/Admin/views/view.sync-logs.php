<?php
$custom_fields = get_option('wp_unioo_sync_custom_fields', []);
?>
<div class="wrap">
  <h1><?php echo __('WP Unioo Sync', WP_UNIOO_SYNC_TEXTDOMAIN); ?></h1>
  <p><?php echo __('Welcome_to the WP Unioo Sync plugin, use this page to manage your sync settings and view sync logs', WP_UNIOO_SYNC_TEXTDOMAIN)?></p>
  <h2><?php echo __('Sync Logs', WP_UNIOO_SYNC_TEXTDOMAIN); ?></h2>
  <button
    data-sync-action="sync_members_list"
    class="button button-primary sync-button"
    sync-type="CSV"
  >
    <?php echo __('Sync Members List', WP_UNIOO_SYNC_TEXTDOMAIN); ?>
  </button>
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
            <td><?php echo esc_html( $log->sync_status ); ?></td>
            <td><?php echo esc_html( date('Y-m-d H:i:s', strtotime($log->sync_time) + 2 * 3600) ); ?></td>
            <td><?php echo esc_html( $log->sync_message ); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<dialog id="sync-file-dialog">
  <div class="dialog-header">
    <h1><?php echo __('Upload sync file', WP_UNIOO_SYNC_TEXTDOMAIN); ?></h1>
    <p><?php echo __('Please select a file to upload for syncing.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
    <small><?php echo __('Accepted file types: CSV, JSON', WP_UNIOO_SYNC_TEXTDOMAIN); ?></small>
  </div>
  <div class="dialog-body">
    <form id="sync-file-form" method="post" enctype="multipart/form-data">
      <label for="sync-file-input"><?php echo __('Upload', WP_UNIOO_SYNC_TEXTDOMAIN); ?></label>
      <input type="file" name="sync_file" id="sync-file-input" accept=".csv, .json" />
    </form>
    <h2><?php echo __('Sync Instructions', WP_UNIOO_SYNC_TEXTDOMAIN); ?></h2>
    <p><?php echo __('To sync your members list, please upload a CSV file with the following columns: Navn, Email, Telefon, Fødselsdato, Adresse, By, Postnummer, Identifikation, Kontingenter (Navne), Ubetalte regninger, Indmeldelsesdato, Udmeldelsesdato, Aktiv betalingsmetode, Nyeste note, Gamertag, Kommune, Køn.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
    <p><?php echo __('Make sure the file is properly formatted and contains all required fields for a successful sync.', WP_UNIOO_SYNC_TEXTDOMAIN); ?></p>
    <p class="lines-found"></p>
    <div id="output" style="white-space: pre-wrap; background: #f0f0f0; padding: 10px; border: 1px solid #ccc;"></div>
  </div>
  <div class="dialog-footer">
    <button id="sync-confirm-button" class="button button-primary"><?php echo __('Sync members', WP_UNIOO_SYNC_TEXTDOMAIN); ?></button>
    <button id="sync-cancel-button" class="button"><?php echo __('Cancel', WP_UNIOO_SYNC_TEXTDOMAIN); ?></button>
  </div>
</dialog>

<style>
  dialog {
    border: none;
    padding: 20px;
    width: 100vw;
    height: auto;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: relative;

    .dialog-header {
      margin-bottom: 20px;
    }
    .dialog-body {
      margin-bottom: 20px;
      gap: 20px;
      min-height: 80%;
    }
    .dialog-footer {
      display: flex;
      justify-content: flex-start;
      gap: 10px;
      position: fixed;
      bottom: 0px;
      background-color: #ccc;
      padding: 15px;
      width: 100%;
      left: 0;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }

    .lines-found {
      font-weight: bold;
    }
  }
</style>

<script>
  const syncFileDialog = document.getElementById('sync-file-dialog');
  const syncConfirmButton = document.getElementById('sync-confirm-button');
  const syncCancelButton = document.getElementById('sync-cancel-button');
  const syncFileInput = document.getElementById('sync-file-input');
  const output = document.getElementById('output');

  output.style.display = 'none';
  syncConfirmButton.style.display = 'none';

  const syncButton = document.querySelector('.sync-button');

  syncButton.addEventListener('click', function() {
    output.innerHTML = '';
    document.querySelector('.lines-found').textContent = '';
    syncFileDialog.showModal();
  });

  syncCancelButton.addEventListener('click', function(){
    output.innerHTML = '';
    output.style.display = 'none';
    document.querySelector('.lines-found').textContent = '';

    syncFileDialog.close();
  })

  // define allowed file types and max file size
  const allowedFileTypes = ['text/csv', 'application/json'];
  const maxFileSize = 5 * 1024 * 1024; // 5MB
  const foundMembers = [];

  // define allowed headers for the csv file and defined custom fields
  const headers = [
    'Navn', 'Email', 'Telefon', 'Fødselsdato', 'Adresse', 'By', 'Postnummer', 'Identifikation', 'Kontingenter (Navne)', 'Ubetalte regninger', 'Indmeldelsesdato', 'Udmeldelsesdato', 'Aktiv betalingsmetode', 'Nyeste note'
  ]
  headers.push(...Object.keys(<?php echo json_encode($custom_fields); ?>));

  syncFileInput.addEventListener('change', function(event) {
    let foundLines = 0;
    // clear previous output
    // create a table element to display the file content
    const table = document.createElement('table');
    table.classList.add('widefat', 'fixed', 'striped');

    // creating tbody for the table
    const tbody = document.createElement('tbody');
    table.appendChild(tbody);

    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();

      reader.onload = function(e) {
          const content = e.target.result;

          // split content into lines and log each line
          const lines = content.split('\n');
          lines.forEach((line, index) => {
            foundLines++;
            // console.log(`Line ${index + 1}: ${line}`);

            // when passed the first line, we can assume it's the header and skip it
            if ( index === 0 ) {
              return;
            }

            const memberObject = {};

            const cells = line.split(';');
            const row = document.createElement('tr');

            cells.forEach((cell, cellIndex) => {
              memberObject[headers[cellIndex]] = cell.trim();
              const td = document.createElement('td');
              td.textContent = cell.trim();
              row.appendChild(td);
            });

            tbody.appendChild(row);
            foundMembers.push(memberObject);
          });

          output.appendChild(table);
          document.querySelector('.lines-found').textContent = `Found ${foundLines} lines in the file.`;
          output.style.display = "block";
          syncConfirmButton.style.display = "inline-block";
          console.log(foundMembers);
      };
      reader.readAsText(file);
    }
  })

  syncConfirmButton.addEventListener('click', function(){
    // get form data
    console.log('Syncing members list with Unioo...', foundMembers);
    fetch('<?php echo esc_url(rest_url('wp-unioo-sync/v1/members/import-csv')); ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
      },
      body: JSON.stringify({ members: foundMembers })
    }).then(response => response.json())
      .then(data => {
        console.log('Sync response: ', data);
        alert('Sync completed successfully!');
        // output.innerHTML = '';
        // output.style.display = 'none';
        // document.querySelector('.lines-found').textContent = '';
        // syncConfirmButton.style.display = 'none';
        // syncFileDialog.close();
      })
  })

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
</script>