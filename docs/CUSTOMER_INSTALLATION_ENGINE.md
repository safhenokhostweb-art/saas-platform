# Customer Installation Engine — Architecture

**Document ID:** CIE-ARCH-001  
**Phase:** PHASE-002  
**Status:** ARCHITECTURE_COMPLETE  
**Date:** 2026-06-27  
**Scope:** Architecture, interfaces, services, jobs — no UI implementation

---

## 1. Purpose

The Customer Installation Engine (CIE) is the only supported mechanism for deploying customer sites. It supersedes any manual deployment procedure. Every customer deployment — whether on our VPS, a remote VPS, or independent hosting — flows through this engine.

The engine reuses existing platform services. It does not duplicate logic from the Restore Engine, Backup Engine, or Platform Installer.

---

## 2. Architectural Position

```
Platform Admin
    │
    ▼
CustomerInstallationOrchestrator           ← new
    │
    ├── RemoteConnectionService            ← new
    ├── CustomerReleasePackageService      ← new (wraps ArvanBackupService)
    ├── CustomerEnvironmentGenerator       ← new (wraps EnvironmentWriter)
    ├── CustomerDatabaseProvisioner        ← new
    ├── CustomerLicenseProvisioner         ← new
    ├── CustomerInstallationStateManager   ← new
    ├── CustomerInstallationRollbackService ← new
    │
    ├── ArvanBackupService                 ← REUSED (package download)
    ├── BaselineManifestService            ← REUSED (SHA-256 verification)
    ├── RestoreService                     ← REUSED (Mode 2 — Restore from Backup)
    ├── DrReadinessScoreService            ← REUSED (post-install health check)
    ├── EnvironmentWriter                  ← REUSED (.env generation)
    ├── InstallerValidator                 ← REUSED (pre-flight checks)
    └── SlackAlertService                  ← REUSED (notifications)
```

**Rule:** The engine consumes existing services via their interfaces. No service logic is re-implemented inside CIE.

---

## 3. Installation Modes

| Mode | ID | Description |
|------|----|-------------|
| Fresh Customer Installation | MODE_FRESH | Full pipeline from zero on target server |
| Restore Customer From Backup | MODE_RESTORE | Redeploy + restore DB/storage from Arvan backup |
| Clone Existing Customer | MODE_CLONE | Copy from existing customer to new customer/server |
| Move Customer To Another Server | MODE_MOVE | Install on new server, restore, cutover, decommission |
| Reinstall Existing Customer | MODE_REINSTALL | Preserve data, fresh software install, restore |

---

## 4. Server Types

| Type | Driver | Status |
|------|--------|--------|
| Our VPS (87.107.12.81) | SSH/SFTP (`SshRemoteDriver`) | Phase 1 |
| Remote VPS (customer-owned) | SSH/SFTP (`SshRemoteDriver`) | Phase 1 |
| Independent Linux Server | SSH/SFTP (`SshRemoteDriver`) | Phase 1 |
| DirectAdmin | `DirectAdminDriver` | Future |
| cPanel / WHM | `CpanelDriver` | Future |
| Docker | `DockerDriver` | Future |
| Kubernetes | `KubernetesDriver` | Future |
| Cloud (AWS/Hetzner) | `CloudProvisionDriver` | Future |

---

## 5. Service Interfaces

### 5.1 RemoteConnectionInterface

```php
namespace App\Services\CustomerInstallation\Contracts;

interface RemoteConnectionInterface
{
    public function connect(): void;
    public function testConnection(): ConnectionTestResult;
    public function execute(string $command, array $env = []): CommandResult;
    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void;
    public function download(string $remotePath, string $localPath): void;
    public function fileExists(string $remotePath): bool;
    public function diskFree(string $path): int;          // bytes
    public function disconnect(): void;
}
```

### 5.2 InstallationStageInterface

```php
namespace App\Services\CustomerInstallation\Contracts;

interface InstallationStageInterface
{
    public function name(): string;                        // stage identifier
    public function label(): string;                       // Persian display label
    public function execute(InstallationContext $ctx): StageResult;
    public function rollback(InstallationContext $ctx): void;
    public function canRollback(): bool;
    public function estimatedSeconds(): int;
}
```

### 5.3 CustomerPackageInterface

```php
namespace App\Services\CustomerInstallation\Contracts;

interface CustomerPackageInterface
{
    public function latestVersion(): string;
    public function getManifest(string $version): PackageManifest;
    public function downloadLocally(string $version): string; // returns temp path
    public function verify(string $localPath, string $expectedSha256): bool;
    public function uploadToRemote(
        string $localPath,
        RemoteConnectionInterface $conn,
        string $remotePath
    ): void;
}
```

### 5.4 InstallationStateInterface

```php
namespace App\Services\CustomerInstallation\Contracts;

interface InstallationStateInterface
{
    public function create(CustomerInstallation $installation): void;
    public function recordStageStart(int $installationId, string $stage): void;
    public function recordStageComplete(int $installationId, string $stage, array $output): void;
    public function recordStageFailure(int $installationId, string $stage, \Throwable $e): void;
    public function recordRollbackCheckpoint(int $installationId, string $stage, array $snapshot): void;
    public function getState(int $installationId): InstallationState;
    public function markComplete(int $installationId): void;
    public function markFailed(int $installationId, string $reason): void;
}
```

---

## 6. Data Transfer Objects

```php
// Customer input collected by wizard
class CustomerInstallRequest
{
    public string $company;
    public string $ownerName;
    public string $email;
    public string $phone;
    public string $language;      // fa / en
    public string $timezone;      // Asia/Tehran
    public string $currency;      // IRR / USD
    public string $domain;
    public string $licenseType;
    public string $themeSlug;     // theme selection hook
    public array  $modules;       // enabled module slugs
    public string $backupPolicy;  // daily / weekly / manual
    public array  $smtp;          // host, port, user, pass, encryption
    public bool   $sslEnabled;
    public int    $remoteServerId;
    public string $installMode;   // MODE_FRESH | MODE_RESTORE | ...
    public ?int   $sourceInstallationId; // for MODE_CLONE / MODE_MOVE
    public ?string $restoreBackupKey;    // for MODE_RESTORE
}

// Per-stage result
class StageResult
{
    public bool   $success;
    public string $stage;
    public array  $output;
    public ?string $error;
    public int    $durationMs;
    public array  $rollbackSnapshot; // data needed for rollback
}

// Runtime context passed through pipeline
class InstallationContext
{
    public CustomerInstallation $installation;
    public CustomerInstallRequest $request;
    public RemoteConnectionInterface $connection;
    public array $stageOutputs = [];    // accumulated outputs per stage
    public array $rollbackStack = [];   // LIFO for rollback
}

// Remote server connection test result
class ConnectionTestResult
{
    public bool   $connected;
    public string $os;
    public string $phpVersion;
    public array  $phpExtensions;
    public int    $diskFreeBytes;
    public int    $pingMs;
    public ?string $error;
}
```

---

## 7. Database Schema

### customer_installations

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| site_id | bigint FK → sites | nullable for fresh mode (populated on completion) |
| remote_server_id | bigint FK → customer_remote_servers | |
| install_mode | enum | MODE_FRESH, MODE_RESTORE, MODE_CLONE, MODE_MOVE, MODE_REINSTALL |
| status | enum | pending, running, complete, failed, rolling_back, rolled_back |
| customer_company | string | |
| customer_email | string | |
| customer_domain | string | |
| package_version | string | customer-site release version installed |
| package_sha256 | string | verified checksum |
| current_stage | string | last stage name (for resume) |
| completed_stages | json | array of completed stage names |
| started_at | timestamp | |
| completed_at | timestamp | nullable |
| failed_at | timestamp | nullable |
| failure_reason | text | nullable |
| installer_user_id | bigint FK → users | admin who initiated |
| request_payload | json | encrypted CustomerInstallRequest |
| created_at / updated_at | timestamp | |

**Rule:** `site_id` is nullable only during installation. On completion it is populated. Multi-tenant scoping does not apply to this table (platform admin only).

### customer_installation_logs

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| installation_id | bigint FK | |
| stage | string | |
| event | enum | started, completed, failed, rolled_back, checkpoint |
| message | text | |
| output | json | nullable (command output) |
| duration_ms | int | nullable |
| created_at | timestamp | |

### customer_remote_servers

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| label | string | human-readable name |
| type | enum | ssh, docker, cpanel, directadmin |
| host | string | |
| port | int | default 22 |
| username | string | |
| auth_method | enum | key, password |
| private_key_encrypted | text | AES-256-GCM encrypted, nullable |
| password_encrypted | string | AES-256-GCM encrypted, nullable |
| key_fingerprint | string | server host key fingerprint |
| last_tested_at | timestamp | nullable |
| last_test_result | enum | success, failure, never |
| created_at / updated_at | timestamp | |

**Rule:** No plaintext credentials ever stored. Encrypted with `INSTALLER_MASTER_KEY` env.

### customer_installation_packages

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| version | string | semver e.g. 1.0.4 |
| arvan_key | string | path in Arvan object storage |
| sha256 | string | verified checksum of ZIP |
| manifest_checksum | string | checksum of manifest.json |
| size_bytes | bigint | |
| released_at | timestamp | |
| is_active | bool | only one active at a time |
| release_notes | text | nullable |
| created_at / updated_at | timestamp | |

### customer_install_rollbacks

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| installation_id | bigint FK | |
| stage | string | stage at which checkpoint was taken |
| snapshot | json | stage-specific rollback data |
| executed_at | timestamp | nullable (null = not yet rolled back) |
| created_at | timestamp | |

---

## 8. Job Architecture

### CustomerInstallationPipelineJob

```
Queue: customer-install
Timeout: 3600s
Tries: 1 (no silent retry on infrastructure ops)
Failed: releases pipeline lock, marks installation as failed, fires SlackAlert
```

Executes the full ordered stage list by dispatching individual stage jobs or running inline (configurable).

### CustomerInstallationStepJob

```
Queue: customer-install
Timeout: 600s per step
Tries: 1
```

Executes one named stage. Records start/complete/failure via `InstallationStateInterface`.

### CustomerInstallationRollbackJob

```
Queue: customer-install-rollback
Timeout: 1800s
Tries: 1
```

Pops rollback stack and executes each rollback in reverse order.

### CustomerPackageUploadJob

```
Queue: customer-upload
Timeout: 1800s
Tries: 3 (transient network failures recoverable)
```

SFTP upload of release ZIP with progress reporting to Redis channel.

---

## 9. Wizard — Customer Information Collection

The wizard collects these fields before initiating the pipeline:

```
Section 1 — Customer Identity
  Company name
  Owner full name
  Email address
  Phone number (mobile, for SMS)

Section 2 — Locale
  Language (fa / en)
  Timezone
  Currency

Section 3 — Hosting
  Domain (with/without www)
  Target server (select from customer_remote_servers)
  SSL auto-provision (yes / no)

Section 4 — License & Modules
  License type (basic / professional / enterprise)
  Module selection (checkboxes: forms / tickets / education / ...)

Section 5 — Theme
  Theme selection (select from marketplace — hook only, no theme impl)

Section 6 — Backup Policy
  Backup schedule (daily 02:00 / weekly / manual)
  Retention (7 / 14 / 30 days)

Section 7 — SMTP
  Host, port, username, password, encryption

Section 8 — Installation Mode
  MODE_FRESH / MODE_RESTORE / MODE_CLONE / MODE_MOVE / MODE_REINSTALL
  (conditional fields based on mode)
```

Wizard collects, validates, encrypts the `request_payload`, and creates the `customer_installations` record. Pipeline job is then dispatched.

---

## 10. Theme Hook Architecture

Theme selection exists as a hook point during installation. No theme is implemented yet. The pipeline reserves stage `INSTALL_THEME` which:

1. Reads `$ctx->request->themeSlug`
2. If non-empty, fires `CustomerThemeInstalled` event with `(string $themeSlug, InstallationContext $ctx)`
3. If no listener registered, stage completes as SKIPPED (non-blocking)

Future theme implementations subscribe to this event. No redesign of the pipeline will be required.

---

## 11. PSDS Integration Points

The engine exposes these integration points for PSDS (Platform-as-a-Service Design System):

- **Theme slot:** `INSTALL_THEME` stage + `CustomerThemeInstalled` event
- **Module slot:** `modules` array in `CustomerInstallRequest` + stage `CONFIGURE_MODULES`
- **Config slot:** Environment generation is delegated to `CustomerEnvironmentGenerator` which accepts extension callbacks
- **Post-install slot:** `CustomerInstallationCompleted` event fired on pipeline success

---

## 12. Admin Center — Customer Deployment

**Route prefix:** `/admin/infrastructure/customer-deployment`

| Route | Purpose |
|-------|---------|
| `GET /` | Installation Center overview |
| `GET /create` | Wizard: new installation |
| `POST /` | Submit wizard, dispatch pipeline |
| `GET /{id}` | Installation detail + live log stream |
| `GET /{id}/logs` | Full audit log |
| `POST /{id}/rollback` | Trigger rollback job |
| `GET /servers` | Remote server list |
| `POST /servers` | Add remote server |
| `DELETE /servers/{id}` | Remove remote server |
| `POST /servers/{id}/test` | Test connection |
| `GET /packages` | Release package registry |

---

## 13. Extension Architecture

Adding a new server driver requires only:

1. Implement `RemoteConnectionInterface` → e.g., `DockerDriver`
2. Register in `config/customer_installation.php` under `drivers`
3. Add to `customer_remote_servers.type` enum via migration

No changes to the pipeline or orchestrator are needed.

---

## 14. Constraints (Never Violate)

1. **Never Git clone** or `composer install` on production remote servers
2. **Always use pre-built release ZIPs** from Arvan with verified SHA-256
3. **Rollback before any destructive remote operation** — checkpoint first
4. **No plaintext passwords** in DB or logs — AES-256-GCM only
5. **`site_id` nullable during install only** — set on completion
6. **All remote commands logged** with timestamp, stage, and output
7. **Pipeline state persisted to DB** at every stage — not in memory only

---

_Generated: 2026-06-27 | Phase: PHASE-002 | PHASE-002_ARCHITECTURE_COMPLETE_
