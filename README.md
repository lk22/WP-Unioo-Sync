# WP Unioo Sync

WP Unioo Sync is a WordPress plugin for synchronizing member data from Unioo into a WordPress site. It is built for organizations that manage memberships in Unioo but still need WordPress users, member metadata, and sync logs available inside their WordPress installation.

The plugin provides an admin interface for configuration, authenticates against the Unioo API, imports members through a sync workflow, and stores the result either as WordPress user meta or in a dedicated custom database table.

## What the Plugin Does

- Connects WordPress to the Unioo API through a configurable GraphQL endpoint and bearer token.
- Supports automatic token regeneration when the API responds with an unauthorized error.
- Creates or updates WordPress users based on Unioo member records.
- Optionally stores synced members in a dedicated database table instead of user meta.
- Logs sync results in a separate log table visible from the WordPress admin area.
- Supports mapping additional custom fields from Unioo into the local sync payload.
- Can restrict synchronization to members with an active membership only.
- Allows choosing which Unioo field becomes the WordPress username.
- Allows generating random passwords or mapping a password field from the member payload.

## Main Use Cases

- Keep WordPress user accounts aligned with members managed in Unioo.
- Maintain a member directory or member-related data in WordPress without managing those records manually.
- Store a local copy of selected member fields for reporting, integration, or custom theme/plugin work.
- Audit sync activity through stored sync logs in the WordPress admin area.

## Features

### Admin Settings

The plugin registers a WordPress admin menu named WP Unioo Sync with dedicated pages for settings and sync logs.

Available settings include:

- GraphQL endpoint URL
- Bearer token / API key
- Unioo username
- Unioo password
- Auto-generate API key on unauthorized response
- Custom field mapping in JSON format
- Toggle for storing members in a custom table
- Toggle for requiring active membership
- Default username field
- Default password field

### Sync Behavior

During synchronization, the plugin:

1. Fetches paginated members from Unioo.
2. Checks whether a matching WordPress user already exists.
3. Creates a new subscriber user when needed.
4. Updates existing users when a match is found.
5. Optionally deletes users when membership restrictions are enabled and the member should no longer be kept active.
6. Stores the member payload either in WordPress user meta or in a custom members table.
7. Writes success or failure details to the sync log table.

### Storage Options

The plugin creates two database tables on activation:

- `wp_unioo_sync`: stores sync status, timestamps, and messages.
- `wp_unioo_members`: stores synced member data when custom table storage is enabled.

If custom member table storage is disabled, member data is written to user meta on the related WordPress user instead.

### Custom Fields

Custom fields can be configured through the admin settings as JSON. These fields are appended to the sync payload and can also be added as columns to the custom members table.

Example:

```json
{
  "Gamertag": "gamertag",
  "Kommune": "municipality",
  "Køn": "gender"
}
```

## Requirements

- WordPress 5.0 or newer
- PHP 7.0 or newer
- A working Unioo account with API access
- Valid Unioo credentials and, when needed, a bearer token

## Installation

### Install in WordPress

1. Clone or copy this plugin into your WordPress plugins directory.
2. Install PHP dependencies with Composer by running `composer install`.
3. Activate the plugin in the WordPress admin.
4. Open WP Unioo Sync in the admin menu.
5. Enter the Unioo endpoint and credentials.
6. Save settings and run a sync.

### Local Development

This repository is structured as a WordPress plugin and includes tooling for testing and static analysis.

Install dependencies:

```bash
composer install
```

Run tests:

```bash
vendor/bin/pest
```

Run PHPStan:

```bash
vendor/bin/phpstan analyse
# if running low on memory on analyze command - add --memory-limit=512M or 1G
vendor/bin/phpstan analyse --memory-limit=512M
```

Run PHPCS:

## Configuration Guide

### GraphQL Endpoint URL

The GraphQL endpoint used to fetch member data from Unioo. If no URL is set in the client, the plugin falls back to `https://api.unioo.io/graphql`.

### API Key / Bearer Token

The bearer token used for authenticated API requests.

### Auto-Generate API Key on Unauthorized Response

When enabled, the plugin attempts to authenticate again and store a fresh bearer token if a sync request receives an unauthorized response.

### Unioo Username and Password

These credentials are used when the plugin authenticates against Unioo to obtain a bearer token.

### Custom Field Mapping

Provide JSON that maps additional fields from the Unioo payload into your local synced data.

### Use Custom Members Table

When enabled, synced member data is written to the `wp_unioo_members` table instead of WordPress user meta. This is useful when:

- You want member data separated from WordPress user records.
- You expect large sync volumes.
- You need easier SQL-level access to synced member information.

### Required Membership

When enabled, synchronization only keeps users that satisfy the plugin's membership checks. Members flagged as not eligible for sync can be skipped or removed depending on the flow.

### Default Username Field

Controls which Unioo field becomes the WordPress username when creating users. The field is configured using template syntax such as:

```text
{{Email}}
```

or

```text
{{Nickname}}
```

### Default User Password Field

Controls how passwords are assigned to created WordPress users.

Supported patterns:

- `generate_random` to create a random password for each user.
- A fixed plain-text value.
- A mapped Unioo field using `{{field_name}}` syntax.

## Admin Workflow

After activation, the plugin adds a WP Unioo Sync menu in the WordPress admin.

From there you can:

- Configure API settings.
- Review sync logs.
- Trigger synchronization actions.
- Import member data through the plugin's sync interface.

The sync log screen stores a history of sync messages with timestamps and status values, making it easier to troubleshoot failures and verify successful imports.

## API and Integration Notes

- The plugin contains a REST API controller under the namespace `wp-unioo-sync/v1`.
- It also contains AJAX-based sync handling for admin-triggered operations.
- Unioo data is fetched in pages and processed sequentially.
- The Unioo client supports token refresh and authentication flows through the Unioo API endpoints.

## Project Structure

Key files and directories:

- `wp-unioo-sync.php`: plugin bootstrap and hook registration
- `src/WPUniooSyncAdminMenu.php`: WordPress admin settings and menu pages
- `src/WPUniooSyncRestAPI.php`: REST sync controller
- `src/Admin/Unioo/UniooClient.php`: Unioo API client and authentication logic
- `src/Admin/Unioo/Sync/`: member sync logic
- `functions.php`: database helpers and sync log helpers
- `tests/`: unit and feature tests

## Quality Tooling

This repository includes:

- Pest for automated tests
- PHPStan for static analysis
- WordPress Coding Standards through PHPCS
- GitHub Actions security auditing via Composer Audit

The PHPStan configuration uses the WordPress extension and may require a higher memory limit during analysis.

## Status

This plugin is intended for projects that use Unioo as the source of truth for membership data and WordPress as the operational frontend, member portal, or integration surface.

If you are extending the plugin, the best place to start is the admin settings class, the Unioo client, and the sync processor classes.
