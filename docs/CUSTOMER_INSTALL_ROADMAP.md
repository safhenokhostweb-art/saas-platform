# Customer Installation Engine — Implementation Roadmap

**Document ID:** CIE-ROAD-001  
**Phase:** PHASE-002  
**Status:** ARCHITECTURE_COMPLETE — AWAITING IMPLEMENTATION START  
**Date:** 2026-06-27

---

## Phase Breakdown

Total estimated implementation: **12–16 working days**

---

## Phase A — Foundation (3 days)

**Goal:** Infrastructure layer. No UI. No pipeline stages yet.

### A.1 — Data Layer (1 day)

Create migrations:
- `create_customer_installations_table`
- `create_customer_installation_logs_table`
- `create_customer_remote_servers_table`
- `create_customer_installation_packages_table`
- `create_customer_install_rollbacks_table`

Create Eloquent models:
- `App\Models\CustomerInstallation`
- `App\Models\CustomerInstallationLog` (no update/delete)
- `App\Models\CustomerRemoteServer`
- `App\Models\CustomerInstallationPackage`
- `App\Models\CustomerInstallRollback`

Create DTOs:
- `CustomerInstallRequest`
- `StageResult`
- `InstallationContext`
- `ConnectionTestResult`
- `CommandResult`
- `PackageManifest`

### A.2 — Interfaces (0.5 day)

Define all contracts:
- `RemoteConnectionInterface`
- `InstallationStageInterface`
- `CustomerPackageInterface`
- `InstallationStateInterface`

### A.3 — CredentialVault (0.5 day)

Implement `CredentialVault`:
- AES-256-GCM encrypt/decrypt
- `php artisan installer:generate-master-key` command
- Unit tests: encrypt → decrypt roundtrip, key rotation

### A.4 — Config File (0.5 day)

Create `config/customer_installation.php` with all tunable values.

### A.5 — Gates (0.5 day)

Register `manage_installations` and `view_installations` gates in `AuthServiceProvider`.

**Deliverable:** All tables, models, interfaces, CredentialVault, config, gates. Zero pipeline logic yet.

---

## Phase B — Remote Connection (2 days)

**Goal:** Reliable SSH connection and command execution.

### B.1 — SshRemoteDriver (1 day)

Implement `SshRemoteDriver implements RemoteConnectionInterface`:
- phpseclib v3 integration
- KEY and PASSWORD auth methods
- Host key fingerprint capture and verification
- `execute()` with timeout, env vars, output capture
- `upload()` with chunked SFTP and progress reporting
- `testConnection()` — full 9-step test sequence

### B.2 — RemoteConnectionService (0.5 day)

Implements driver selection via `buildDriver(CustomerRemoteServer $server)`.

### B.3 — Tests (0.5 day)

Unit tests for `SshRemoteDriver` using a mock phpseclib connection.
Integration tests: optional (requires a test VPS — skip for Phase A/B).

**Deliverable:** Working SSH connection layer. Can execute commands and upload files.

---

## Phase C — Package Layer (1 day)

**Goal:** Download, verify, and upload release ZIPs.

### C.1 — CustomerReleasePackageService (0.5 day)

Implements `CustomerPackageInterface`:
- `latestVersion()` — queries `customer_installation_packages`
- `downloadLocally()` — wraps `ArvanBackupService::download()`
- `verify()` — wraps `BaselineManifestService` SHA-256 logic
- `uploadToRemote()` — delegates to `SshRemoteDriver::upload()`

### C.2 — CustomerPackageUploadJob (0.5 day)

Dispatched by pipeline for large file transfers.
Queue: `customer-upload`, timeout: 1800s, tries: 3.

**Deliverable:** Package download → verify → upload pipeline works end-to-end.

---

## Phase D — Pipeline Core (3 days)

**Goal:** All 20 stages implemented and wired into the orchestrator.

### D.1 — InstallationStateManager (0.5 day)

Implements `InstallationStateInterface` — persists stage events to `customer_installation_logs`.

### D.2 — Stage Classes (1.5 days)

One class per stage in `App\Services\CustomerInstallation\Stages\`:

```
ValidateServerStage
ValidatePhpStage
ValidateExtensionsStage
ValidateDiskStage
ValidatePermissionsStage
UploadPackageStage
VerifyPackageStage
ExtractPackageStage
GenerateEnvironmentStage
CreateDatabaseStage
RunMigrationsStage
RunSeederStage
ConfigureStorageStage
ConfigureQueueStage
ConfigureSchedulerStage
ProvisionLicenseStage
InitializeBackupStage
ConfigureMonitoringStage
InstallThemeStage
CustomerReadyStage
```

Each implements `InstallationStageInterface`.

### D.3 — CustomerInstallationOrchestrator (0.5 day)

Builds stage list by mode. Executes stages via `CustomerInstallationPipelineJob`. Handles resume logic (`completed_stages` check).

### D.4 — CustomerInstallationPipelineJob (0.5 day)

Main job: queue `customer-install`, timeout 3600s, tries 1.
Opens SSH connection, passes to InstallationContext, runs stages, closes connection in `finally`.

**Deliverable:** Full pipeline executable end-to-end for MODE_FRESH.

---

## Phase E — Rollback Engine (1 day)

**Goal:** Reliable rollback on failure.

### E.1 — CustomerInstallationRollbackService (0.5 day)

Reads rollback stack from `customer_install_rollbacks`. Executes each rollback in LIFO order.

### E.2 — CustomerInstallationRollbackJob (0.5 day)

Dispatched on pipeline failure. Queue: `customer-install-rollback`.

**Deliverable:** Failed installations roll back cleanly.

---

## Phase F — Mode Variants (2 days)

**Goal:** Support all 5 installation modes.

### F.1 — MODE_RESTORE (0.5 day)

Wire `RestoreService::restoreTenantDatabase()` and `restoreTenantStorage()` between stages 09 and 13.
Skip stages 10–12.

### F.2 — MODE_CLONE (0.5 day)

Add `ExportSourceStage`, `ImportToTargetStage`, `AnonymizeIfRequiredStage`.
Fire `CustomerCloneAnonymize` event.

### F.3 — MODE_MOVE (0.5 day)

Add `BackupSourceStage`, `RestoreOnTargetStage`, `DnsCutoverReadyStage`, `DecommissionSourceStage` (manual gate).

### F.4 — MODE_REINSTALL (0.5 day)

Trigger forced backup before extraction. Restore after stage 09.

**Deliverable:** All 5 modes operational.

---

## Phase G — Admin Center (2 days)

**Goal:** Installation Center UI.

### G.1 — Controller + Routes (0.5 day)

`CustomerDeploymentController` — all routes from architecture doc.

### G.2 — Views (1 day)

- Installation overview (history table, status badges)
- New installation wizard (8 sections, multi-step Alpine.js)
- Installation detail with live log stream (SSE)
- Remote servers list + add form + test button
- Package registry view

### G.3 — SSE Progress Stream (0.5 day)

`GET /{id}/stream` → reads from `install_progress_{uuid}` Redis list → EventSource events.

**Deliverable:** Admin can create installations, view progress, view logs, manage servers.

---

## Phase H — Tests + Hardening (1.5 days)

### H.1 — Unit Tests

- `CredentialVaultTest` — encrypt/decrypt/rotate
- `SshRemoteDriverTest` — mock phpseclib
- Each stage class — mock connection, verify output/rollback
- `CustomerInstallationOrchestratorTest` — stage ordering, skip logic

### H.2 — Integration Tests

- MODE_FRESH end-to-end against test VPS (if available)
- Rollback test: force failure at stage 12, verify rollback stack executed
- Package verification: corrupt ZIP fails cleanly

### H.3 — Security Hardening

- Verify no credentials in logs
- Verify host key mismatch aborts
- Verify path sanitization on all remote paths

**Deliverable:** Test suite green. Security review PASS.

---

## Phase I — Documentation + Admin Menu Wire-up (0.5 day)

- Add `customer_deployment` to `AdminMenu.php` under زیرساخت group
- Add sidebar icon: deployment/server icon
- Update `docs/project-memory/` files

---

## Summary

| Phase | Description | Days | Dependency |
|-------|-------------|------|------------|
| A | Foundation | 3 | — |
| B | Remote Connection | 2 | A |
| C | Package Layer | 1 | A, B |
| D | Pipeline Core | 3 | A, B, C |
| E | Rollback Engine | 1 | D |
| F | Mode Variants | 2 | D, E |
| G | Admin Center | 2 | D |
| H | Tests + Hardening | 1.5 | All |
| I | Docs + Menu | 0.5 | All |
| **Total** | | **16** | |

---

## Parallel Execution Options

Phases G (Admin Center) and F (Mode Variants) can run in parallel after Phase D is complete. This can reduce the total timeline to **12 working days** with two parallel workstreams.

---

## Future Phases (Post Phase-002)

| Phase | Feature | Trigger |
|-------|---------|---------|
| CIE-FUTURE-1 | DirectAdmin driver | Customer demand |
| CIE-FUTURE-2 | cPanel / WHM driver | Customer demand |
| CIE-FUTURE-3 | Docker driver | Internal DevOps need |
| CIE-FUTURE-4 | Kubernetes driver | Scale milestone |
| CIE-FUTURE-5 | Cloud provisioning (Hetzner/AWS) | SaaS expansion |
| CIE-FUTURE-6 | One-click Customer Portal | Self-service installs |
| CIE-FUTURE-7 | Automated DNS management | Domain registrar API |

---

## Prerequisite Decisions Before Start

| Item | Status | Owner |
|------|--------|-------|
| `phpseclib/phpseclib` added to composer.json | Pending | Backend |
| `INSTALLER_MASTER_KEY` env defined for production | Pending | DevOps |
| Arvan release package bucket structure agreed | Pending | DevOps |
| Test VPS available for integration tests | Pending | DevOps |
| `manage_installations` gate scope (who gets it) | Pending | Product |

---

_Generated: 2026-06-27 | Phase: PHASE-002 | CIE-ROAD-001_
