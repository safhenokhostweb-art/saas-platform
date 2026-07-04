# cPanel Required Operations Map

## Scope

This report maps the legacy customer-installation pipeline to the operations a real cPanel target must provide. It does not define a new deployment contract or implement a cPanel client.

The pipeline opens one connection before stage execution and disconnects it afterward. During installation it uses these `RemoteConnectionInterface` operations:

| Operation | Legacy usage |
|---|---|
| `connect()` | Required once by the orchestrator before all stages. |
| `testConnection()` | Not called by the installation orchestrator or stages. |
| `execute()` | Core primitive used throughout the stages for shell commands, file manipulation, database CLI operations, service control, and Artisan. |
| `upload()` | Required indirectly by `UPLOAD_PACKAGE` through the package service. The SSH implementation transfers through SFTP, with a same-server shell-copy optimization. |
| `download()` | Required by `INITIALIZE_BACKUP` to retrieve the compressed baseline database dump. |
| `fileExists()` | Not called by the current installation stages; existence checks use shell `test`, `stat`, or `find` through `execute()`. |
| `diskFree()` | Required by `VALIDATE_DISK` for `/var/www` and `/tmp`; the SSH implementation delegates to `df`. |
| `disconnect()` | Required once in the orchestrator's `finally` block. |

There are no contract methods named `test`, `exists`, `directoryExists`, or `diskSpace`. Their current equivalents are `testConnection`, shell checks through `execute`, and `diskFree`.

## Stage-by-Stage Mapping

Legend: **Direct** means a normal cPanel account API can represent the operation without arbitrary command execution. **Partial** means cPanel APIs cover only part of the legacy behavior and the stage must change. **No** means the legacy operation is not an account API capability.

| Stage | Remote method and required operation | cPanel API | SSH | SFTP | Redesign |
|---|---|---:|---:|---:|---:|
| Pipeline lifecycle | `connect`, `disconnect`; establish authenticated target access and release resources | Direct for stateless HTTP authentication | Required by legacy command path | No | Yes, separate API and shell sessions |
| `VALIDATE_SERVER` | `execute`: `uname`, OS release discovery | No | Yes | No | Yes; validate cPanel account capabilities instead of host OS |
| `VALIDATE_PHP` | `execute`: CLI PHP version and PHP-FPM service status | Partial | Yes for exact legacy checks | No | Yes; query account PHP selection and remove service-level assumptions |
| `VALIDATE_EXTENSIONS` | `execute`: `php -m` | Partial | Yes for exact CLI runtime | No | No if SSH is mandatory; otherwise yes |
| `VALIDATE_DISK` | `diskFree`: free bytes for `/var/www` and `/tmp` | Partial | Yes for path-level free space | No | Yes; use account quota/usage and cPanel-owned paths |
| `VALIDATE_PERMISSIONS` | `execute`: create/delete a web-root probe; test writes to `/etc/supervisor/conf.d` and `/etc/cron.d` | Partial | Yes, usually without required root permissions on shared hosting | No | Yes; remove system-directory requirements |
| `UPLOAD_PACKAGE` | indirect `upload`; `execute`: remote `stat` and `rm`; package currently targets `/tmp` | Direct/Partial through file-management APIs in account-owned paths | No if fully redesigned to API file management | Alternative only | Yes; use account home staging, API metadata, and API deletion |
| `VERIFY_PACKAGE` | `execute`: `sha256sum`; delete mismatched package | No for the exact server-side hash workflow | Yes | No | Yes for API-only targets; verify through a trusted bootstrap operation |
| `EXTRACT_PACKAGE` | `execute`: directory checks, counts, removal, `mkdir`, `unzip`/`tar`, move nested contents | Partial | Yes for current behavior | No | Yes; use cPanel archive/file operations and deterministic account paths |
| `GENERATE_ENVIRONMENT` | `execute`: write base64-decoded `.env`; delete on rollback | Direct through file-management APIs | No | Alternative only | Yes; replace shell encoding/write logic |
| `CREATE_DATABASE` | `execute`: root MySQL create database/user/grants and `sed` updates to `.env` | Direct for account-scoped database/user grants; file API for `.env` | No after redesign | No | Yes; root MySQL commands are invalid for normal cPanel accounts |
| `RUN_MIGRATIONS` | `execute`: `php artisan migrate --force` and `migrate:status` | No | Yes | No | Yes for non-SSH accounts; requires a secure one-shot application bootstrap |
| `RUN_SEEDER` | `execute`: `php artisan db:seed` | No | Yes | No | Yes for non-SSH accounts; use the same secure bootstrap mechanism |
| `CONFIGURE_STORAGE` | `execute`: create directories, run `storage:link`, verify/create symlink, `chmod`, `chown` | Partial | Yes for current behavior, subject to account permissions | No | Yes; avoid `www-data` ownership and define a cPanel-safe link strategy |
| `CONFIGURE_QUEUE` | `execute`: write Supervisor config, reload/update Supervisor, inspect worker | No for account-level cPanel | Yes only on privileged/VPS targets | No | Yes; shared cPanel needs cron-driven or externally managed queue execution |
| `CONFIGURE_SCHEDULER` | `execute`: write `/etc/cron.d` and set mode | Direct through cPanel cron APIs, but not at `/etc/cron.d` | No after redesign | No | Yes; create an account cron entry through cPanel |
| `CONFIGURE_DNS` | `execute`: discover public IP with external `curl`/`hostname`; DNS record itself uses the platform DNS provider | Partial | No if target IP is obtained from target metadata/API | No | Yes; do not discover IP by running remote shell commands |
| `CONFIGURE_WEB_SERVER` | `execute`: write/enable Nginx config, detect FPM socket, test and reload Nginx | Partial for domains, document roots, redirects, and SSL; no raw Nginx control | Yes only on privileged/VPS targets | No | Yes; replace the entire host-level Nginx stage with cPanel domain/SSL operations |
| `PROVISION_LICENSE` | `execute`: replace and verify `LICENSE_KEY` in `.env`; platform license issuance is local | Direct through file-management APIs | No | Alternative only | Yes; update `.env` without `sed`/`grep` |
| `INITIALIZE_BACKUP` | `execute`: edit `.env`, read DB credentials, `mysqldump | gzip`, `stat`, cleanup, run Artisan backup; `download`: retrieve baseline dump | Partial; cPanel backup/database APIs do not match this artifact workflow directly | Yes for current behavior | Required only if API download is not used | Yes; define an account-scoped baseline export and retrieval flow |
| `CONFIGURE_MONITORING` | `execute`: Artisan health check or localhost HTTP probe | No for Artisan; external HTTP check is independent of cPanel | No after redesign | No | Yes; verify the public health endpoint from the platform |
| `INSTALL_THEME` | No `RemoteConnectionInterface` call | Not applicable | No | No | No cPanel-specific remote operation |
| `CUSTOMER_READY` | `execute`: write install lock, set owner/mode, remove installation marker | Partial through file-management APIs | No after redesign | Alternative only | Yes; use account ownership defaults and API file operations |

## API Feasibility

### Direct cPanel API candidates

- Authenticate and inspect the cPanel account.
- Create account-scoped databases, users, and grants.
- Create cron entries.
- Manage account-owned files, directories, package upload, extraction, deletion, and content updates where supported by the selected cPanel API version.
- Configure cPanel-owned domains/document roots and SSL functions.
- Read account quota or disk-usage information.

### Operations that remain outside cPanel account APIs

- Arbitrary shell execution.
- Laravel migrations, seeders, Artisan backup, and CLI health commands.
- Root MySQL access.
- Writing `/etc/supervisor/conf.d`, controlling Supervisor, or guaranteeing a persistent queue worker.
- Writing `/etc/cron.d` directly.
- Writing, testing, or reloading raw Nginx configuration.
- Discovering PHP-FPM sockets and controlling system services.
- Applying `www-data` ownership on shared-hosting account files.
- Reproducing the current server-side SHA-256 and baseline dump workflow without a trusted execution mechanism.

FTP or SFTP solves only byte transfer. It does not solve command execution, database provisioning, cron/domain configuration, service management, migrations, or rollback semantics. Adding SFTP to the current cPanel stub therefore cannot make the legacy pipeline complete.

## Typed DeploymentTarget Capability Contract

cPanel support must use explicit boolean capabilities resolved from `DeploymentTarget`; it must not infer support from the driver name or translate arbitrary shell commands. The initial contract is:

| Capability | Type | Required to install | Readiness requirement |
|---|---|---:|---|
| `domain_management` | `bool` | Yes | Create or attach the target domain and enforce the configured document root. |
| `database_management` | `bool` | Yes | Create an account-scoped database/user and grants without root MySQL access. |
| `file_upload` | `bool` | Yes | Upload package artifacts into an account-owned staging path. |
| `file_extract` | `bool` | Yes | Extract and normalize the package into the document root without arbitrary command execution. |
| `ssl_management` | `bool` | Yes | Request, install, or verify TLS for the target domain. |
| `cron_management` | `bool` | Yes | Create and verify account-level scheduler and queue cron entries. |
| `email_management` | `bool` | No | Provision or inspect account-level mail resources when requested by a later workflow. |
| `backup_restore` | `bool` | Yes | Produce, retrieve, and restore an account-scoped recovery artifact with integrity verification. |
| `php_version` | `bool` | Yes | Read and select a supported account-level PHP version for the target domain. |
| `disk_quota` | `bool` | Yes | Read account quota and usage and prove sufficient installation capacity. |

Capability values are evidence-backed booleans, not optimistic driver defaults. A cPanel target is installable only when every capability marked required is `true`, target validation succeeds, and the future secure application bootstrap capability for migrations and seeding succeeds. Unknown, untested, or failed capability checks are treated as `false`.

## Required Credential Schema

The canonical input schema for a cPanel `DeploymentTarget` is:

| Input | Type | Requirement | DeploymentTarget mapping |
|---|---|---|---|
| `host` | non-empty hostname or IP string | Required | `host` |
| `username` | non-empty string | Required | `username` |
| `api_token` | secret string | Required unless password fallback is explicitly used | Encrypt into `api_token_encrypted`; never persist or log plaintext. |
| `password` | secret string or `null` | Optional fallback only | Encrypt into `password_encrypted`; never persist or log plaintext. |
| `port` | integer from `1` to `65535` | Required; use an explicit cPanel API port | `port` |
| `document_root` | normalized account-owned absolute path | Required | Existing `path` column; expose as `document_root` at the typed boundary. |
| `domain` | normalized DNS name | Required | `domain` |

The target uses `connection_driver=cpanel` and token authentication by default. Password fallback must be policy-controlled and must not silently replace an invalid token. Host, domain, port, and document root are configuration, not credentials; secrets remain in the existing encrypted columns.

## Legacy Migration and Bootstrap Plan

1. Add a forward, reversible, idempotent bootstrap migration or command; do not alter or delete `customer_remote_servers` records.
2. Select legacy records in stable primary-key order and create one `DeploymentTarget` per record only when no migration mapping exists. Persist an explicit legacy-source identifier in migration metadata or a dedicated unique mapping column before production backfill so reruns cannot duplicate targets.
3. Copy fields without decrypting secrets: `label -> name`, `type -> type`, `host`, `port`, `username`, `path -> path`, `domain`, encrypted credential columns, key fingerprint, and last-test fields. Normalize legacy auth values to the canonical auth enum during mapping.
4. Preserve SSH behavior: SSH legacy records map to `connection_driver=ssh`, retain their encrypted key/password material, and remain served by the existing SSH installation path until a separately validated cutover. Do not bulk-repoint existing installations.
5. Map only positively identified cPanel records to `connection_driver=cpanel`; ambiguous records remain legacy and are reported for manual review rather than guessed.
6. Initialize migrated cPanel targets as non-installable (`pending` or `inactive`) with all required capability evidence false/unknown. Never infer readiness from stored credentials or a successful legacy SSH test.
7. Run future target validation in two phases: schema validation first, then authenticated capability probes. Store only probe results, timestamps, and sanitized errors; never store response payloads containing secrets.
8. Promote a cPanel target to installable only after all required capabilities in the matrix and the secure migration/seeding bootstrap pass. Any failed or stale required probe fails closed.
9. Cut over new cPanel installations only after targeted tests and an explicit operational approval. Keep legacy records and rollback routing available throughout the compatibility window.
10. Reconciliation must report source record, target record, normalized driver/auth mapping, capability status, and skipped/error reason. Rollback disables or removes only bootstrap-created targets when they are unreferenced; it never deletes legacy records.

### Bootstrap Mapping Matrix

| Legacy value | DeploymentTarget value | Safety rule |
|---|---|---|
| `label` | `name` | Copy verbatim after normal validation. |
| `type=cpanel` | `type=cpanel`, `connection_driver=cpanel` | Start non-installable. |
| SSH/VPS type | Preserve target type, `connection_driver=ssh` | Preserve the working SSH pipeline. |
| `auth_method=key` | canonical SSH key auth | Copy encrypted private key and fingerprint without decryption. |
| API-token auth | canonical cPanel API-token auth | Copy encrypted token without decryption. |
| password auth | canonical password auth | Retain only as explicit fallback. |
| `path` | `path` (`document_root` contract field) | Validate account ownership before activation. |
| test timestamps/results | corresponding target fields | Historical success is not capability evidence. |
## Decision

**Recommended path: D â€” unsupported until DeploymentTarget migration.**

The current pipeline is structurally an SSH pipeline because `execute()` is its dominant primitive. Implementing cPanel UAPI calls behind `execute(string $command)` would create an unsafe command parser and a brittle translation layer. Marking SSH as optional would also be inaccurate: under the legacy stages, SSH is mandatory for migrations, seeding, extraction details, symlinks, backup creation, and several validation steps.

The maintainable enterprise path is to keep cPanel installation explicitly unsupported in the legacy `CustomerRemoteServer` pipeline, then implement cPanel as capability-driven operations under `DeploymentTarget`. A future cPanel target may use cPanel API plus optional SSH, but only after stages call typed deployment capabilities rather than arbitrary shell commands. API-only accounts need redesigned bootstrap, queue, storage-link, health, and backup flows before they can be supported.

## Implementation Readiness

**Ready for full cPanel installation implementation: NO.**

An HTTP API client can be implemented in isolation, but connecting it to the legacy pipeline would not produce a working installation and should not be presented as supported.

## Next Micro-Task

Define the typed capability DTO/enum and capability-probe result contract, including the fail-closed `isInstallable` rule and secure one-shot application bootstrap contract. Do not implement cPanel installation stages, command-string translation, or modify the SSH driver.
