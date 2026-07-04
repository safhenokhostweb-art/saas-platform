# Centralized Customer Deployment Contract

Customer sites never execute an installer. An authenticated platform administrator is the sole deployment initiator, and every deployment runs as a queued remote job controlled by the SaaS platform.

## Canonical Flow

`Admin Panel → Queue Job → Select Driver → Execute Remote Deployment → Verify → Report`

- Packages are immutable ZIP artifacts selected by the central platform.
- Customer-facing and public installer endpoints are forbidden.
- Remote credentials remain in the platform credential vault and are never sent to a customer runtime.

## Deployment Modes

### `FULL_SERVER_INSTALL`

Use for owner-managed VPS, dedicated, or self-hosted servers reached through SSH.

- Supported drivers: `ssh`
- Allowed operations: upload, extract, environment configuration, database creation, migrations, seeders, nginx or Apache configuration, cron, queue workers, SSL, backup, and restore
- Execution path: the centrally queued remote deployment pipeline

### `HOSTING_PANEL_INSTALL`

Use for cPanel, DirectAdmin, shared-hosting, and SFTP-oriented targets where server administration is not available.

- Supported drivers: `cpanel`, `directadmin`, `shared`, `sftp`
- Required capabilities: `file_upload`, `file_extract`, and either `database_management` or `manual_db_credentials`
- Optional capabilities: `cron_management`, `ssl_management`, and `backup_restore`
- Package source: the immutable ZIP recorded by the installation package system, constrained to the configured package cache
- Package target: the configured hosting `document_root` (default `public_html`) plus the immutable ZIP filename; `/var/www` targets are rejected
- Current status: cPanel supports native UAPI upload, native Fileman extraction, and partial environment generation through the Fileman API. Central remote execution is not yet active; other hosting-panel drivers remain blocked until they implement native operations.
- Environment behavior: `.env` is constrained to the configured `document_root`, existing keys and values are preserved, and only missing keys are appended. Manual database credentials are used only when `request_payload.manual_db_credentials.validated` is `true`; otherwise database placeholders are written and database status remains `Pending`.

#### Stage Order

1. `VALIDATE_HOSTING_TARGET`
2. `UPLOAD_PACKAGE`
3. `EXTRACT_PACKAGE`
4. `GENERATE_ENVIRONMENT`
5. `PREPARE_DATABASE`
6. `EXECUTE_REMOTE_DEPLOYMENT`
7. `CONFIGURE_CRON_IF_SUPPORTED`
8. `VERIFY_HTTP`
9. `INITIALIZE_BACKUP_IF_SUPPORTED`
10. `CUSTOMER_READY`

Mandatory capability failures stop the pipeline with the stage and missing capability in the error. Optional cron and backup stages are skipped when unsupported. SSL remains optional provider capability metadata and is not configured by this skeleton.

## Forbidden Hosting-Panel Assumptions

`HOSTING_PANEL_INSTALL` must not require SSH, supervisor, root access, nginx reloads, system cron, `/var/www`, or Linux service control.

## Driver Routing

| Driver | Installation mode | Current behavior |
|---|---|---|
| `ssh` | `FULL_SERVER_INSTALL` | Uses the existing pipeline unchanged |
| `cpanel` | `HOSTING_PANEL_INSTALL` | Uploads through Fileman UAPI, extracts through Fileman API 2 `fileop`, merges missing `.env` keys through Fileman UAPI, then stops before database preparation |
| `directadmin` | `HOSTING_PANEL_INSTALL` | Stops safely because capability probing is not implemented |
| `shared` | `HOSTING_PANEL_INSTALL` | Stops safely because capability probing is not implemented |
| `sftp` | `HOSTING_PANEL_INSTALL` | Stops safely because capability probing is not implemented |

## Why Panel Hosting Cannot Use the SSH Pipeline

The current pipeline assumes command execution and server-level operations for PHP validation, migrations, queues, schedulers, web-server configuration, monitoring, backup, and rollback. Shared-hosting APIs expose a narrower provider-specific capability set and cannot safely emulate root or service control. Routing panel targets through the SSH pipeline would therefore produce partial installs or false success.

Until hosting operations are implemented, a stage with missing mandatory capability fails with a message such as:

> UPLOAD_PACKAGE blocked: missing mandatory capability: file_upload.

If capability evidence exists but its operation adapter is still absent, the stage fails without attempting deployment or reporting success.

## Next Implementation Steps

1. Implement database preparation, including manual credentials.
2. Implement central remote execution and HTTP verification without a customer installer.
3. Add ordered SSH → cPanel API → file-upload fallback, then queue retry after all available drivers fail.
4. Activate DirectAdmin operations and add end-to-end fallback tests.
