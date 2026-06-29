# ENTERPRISE CUSTOMER DELETE ENGINE

> Version: 1.0.0 — PHASE-003H
> Date: 2026-06-29
> Status: CERTIFIED

---

## Overview

The Enterprise Customer Delete Engine (CDE) is a reverse pipeline that safely
and completely removes all artifacts of a customer installation. It is the
authoritative mechanism for customer deletion — no manual SSH commands, no
hardcoded paths, no partial cleanups.

---

## Architecture Principles

### 1. Reverse Pipeline

The delete pipeline is the mirror image of the install pipeline, executed in
reverse order:

```
Install order:   VALIDATE → UPLOAD → EXTRACT → ENV → DB → MIGRATE → QUEUE → SCHEDULER → WEBSERVER → LICENSE → BACKUP → READY
Delete order:    AUDIT → BACKUP → STOP_SERVICES → REMOVE_QUEUE → REMOVE_SCHEDULER → REMOVE_WEBSERVER → REMOVE_STORAGE → REMOVE_FILES → REMOVE_DATABASE → REMOVE_DATABASE_USER → REMOVE_LICENSE → REMOVE_PLATFORM_RECORDS → REMOVE_RECOVERY_METADATA → VERIFY_DELETE
```

### 2. Dynamic Resolution — Zero Hardcoding

Every resource identifier is resolved dynamically from `CustomerInstallation`
metadata and stage output logs:

| Resource | Resolution Source |
|----------|------------------|
| Web root path | `customerSlug()` algorithm (same formula as install) |
| Database name | `CREATE_DATABASE` stage output in `customer_installation_logs` |
| Database user | `CREATE_DATABASE` stage output (rollback snapshot as fallback) |
| Supervisor config | Deterministic: `/etc/supervisor/conf.d/{slug}_worker.conf` |
| Cron path | Deterministic: `/etc/cron.d/{slug}` |
| Nginx config | Deterministic: `/etc/nginx/sites-available/{slug}` |
| License key | `sites.license_key` column |
| Arvan backup key | `customer_installations.initial_baseline_arvan_key` |

### 3. Transaction Properties

| Property | Implementation |
|----------|----------------|
| Transactional | Platform DB removals wrapped in `DB::transaction()` |
| Idempotent | `DROP IF EXISTS`, `rm -f`, `DELETE WHERE` — all idempotent |
| Resumable | Completed stages stored in `customer_deletions.completed_stages`; already-done stages are skipped on retry |
| Retry-safe | Job dispatched with `tries=1`; manual retry creates new deletion record |
| Non-critical failures | Non-critical stage failures log and continue; only critical failures abort |

### 4. Stage Contract

Every stage implements `DeleteStageInterface`:

```php
interface DeleteStageInterface {
    name(): string;            // Machine-readable ID
    label(): string;           // Persian UI label
    isCritical(): bool;        // Abort on failure if true
    estimatedSeconds(): int;
    buildPreSnapshot(ctx): array;  // State before execution
    execute(ctx): DeleteStageResult;
    verify(ctx): array;            // Post-execution checks
    rollback(ctx, snapshot): void; // Undo if needed
}
```

---

## Delete Policy

The admin selects a policy before initiating deletion. The policy controls
what happens to backup artifacts. The core deletion pipeline (nginx, supervisor,
files, DB, license, platform records) always runs regardless of policy.

| Policy | Backup behavior |
|--------|----------------|
| `never_delete_backup` | All backup artifacts (local + Arvan) are preserved |
| `delete_local_backups` | Local recovery repo files deleted; Arvan preserved |
| `delete_remote_backups` | Arvan objects deleted; local preserved |
| `delete_everything` | All backup artifacts deleted; skip final backup |

---

## File Structure

```
app/Services/CustomerDeletion/
  Contracts/
    DeleteStageInterface.php
  Dto/
    DeleteContext.php         ← Dynamic resource resolver
    DeletePolicy.php          ← Enum with shouldDeleteLocal/Remote helpers
    DeleteStageResult.php     ← Immutable result DTO
  Stages/
    AbstractDeleteStage.php
    PreDeleteAuditStage.php
    FinalBackupStage.php
    StopServicesStage.php
    RemoveQueueStage.php
    RemoveSchedulerStage.php
    RemoveWebServerStage.php
    RemoveStorageStage.php
    RemoveFilesStage.php
    RemoveDatabaseStage.php
    RemoveDatabaseUserStage.php
    RemoveLicenseStage.php
    RemovePlatformRecordsStage.php
    RemoveRecoveryMetadataStage.php
    VerifyDeleteStage.php
  CustomerDeleteOrchestrator.php
  CustomerDeleteStateManager.php

app/Jobs/
  CustomerDeletionPipelineJob.php  ← timeout=1800, tries=1

app/Http/Controllers/Admin/Infrastructure/
  CustomerDeleteController.php

database/migrations/
  2026_06_29_400000_create_customer_deletions_table.php
  2026_06_29_400001_create_customer_deletion_logs_table.php

app/Models/
  CustomerDeletion.php
  CustomerDeletionLog.php

resources/views/admin/infrastructure/customer-deployment/
  delete-confirm.blade.php   ← Policy selector + domain confirmation
  delete-progress.blade.php  ← SSE live progress + verification report
```

---

## Database Schema

### customer_deletions

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| installation_id | bigint FK | Links to customer_installations |
| site_id | bigint nullable | Snapshot of site_id at initiation |
| initiated_by | int | Admin user who triggered deletion |
| policy | varchar(50) | Delete policy enum value |
| status | varchar(30) | pending / running / complete / failed |
| current_stage | varchar(100) | Stage currently executing |
| completed_stages | JSON | Array of completed stage names |
| audit_snapshot | JSON | Pre-delete audit results |
| verify_report | JSON | Post-delete verification report |
| failure_reason | text | Failure message if status=failed |
| started_at / completed_at / failed_at | timestamps | |

### customer_deletion_logs

Append-only log of every stage event during deletion. Not subject to immutability
constraint (unlike `customer_installation_logs`) since deletion is a one-time event.

---

## Admin Routes

| Method | Path | Action |
|--------|------|--------|
| GET | `/installations/{id}/delete` | Confirm form (policy + domain) |
| POST | `/installations/{id}/delete` | Initiate deletion pipeline |
| GET | `/deletions/{id}` | Progress view |
| GET | `/deletions/{id}/stream` | SSE real-time stream |

---

## Security

- **Domain confirmation**: Admin must type the exact customer domain before initiating
- **Running guard**: Cannot delete an installation that is currently running
- **Duplicate guard**: If an active deletion already exists, redirects to it
- **Audit trail**: `customer_installation_logs` are never deleted (immutable)
- **License revocation**: `PlatformLicense` record deleted — tenant API calls fail immediately

---

## ADR-018 (to be recorded)

**Decision**: Enterprise Delete Engine replaces all manual SSH cleanup
**Reason**: The old `destroy()` was hardcoded, non-transactional, and left orphaned resources (supervisor config, cron, nginx, DB user)
**Impact**: All customer deletions must go through `CustomerDeleteController::confirm()` → `initiate()`
