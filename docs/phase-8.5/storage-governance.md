# Storage Governance & Attachment Isolation — Phase 8.5

## Overview

Storage governance enforces per-tenant storage quotas, validates attachment isolation, and tracks all storage operations through telemetry.

## Storage Quotas by Plan

| Plan | Quota |
|------|-------|
| trial | 100 MB |
| starter | 1 GB |
| professional | 10 GB |
| enterprise | 100 GB |

## Path Isolation

All tenant files must be stored under `tenants/{tenantId}/`. The `AttachmentIsolationManager` enforces this at every access:

```
Authorization check:
1. extractTenantFromPath(path) → owning tenant
2. if owning_tenant ≠ requesting_tenant → cross-tenant DENIED
3. sanitizePath() → strip ../ and ./ traversals
4. isAllowedExtension() → whitelist: pdf, jpg, png, gif, webp, csv, xlsx, docx, zip, txt
```

## Upload Authorization Flow

```
TenantStorageGovernor.authorizeUpload(tenantId, filename, sizeMb)
    ↓
1. isAllowedExtension() → reject if not in whitelist
2. isExceeded()         → reject if quota exceeded
3. remainingMb()        → reject if file > remaining
    ↓ (allowed)
TenantStorageGovernor.recordUpload()
    → RuntimeStorageQuotaService.recordUsage()
    → AttachmentAccessTelemetry.recordUpload()
```

## Cross-Tenant Access Telemetry

Any cross-tenant path access attempt emits:
```
storage.attachment.cross_tenant_attempt
  requesting_tenant: int
  owning_tenant: int
  target_path: string
```

## Future Object Storage Readiness

The `TenantStorageGovernor` is vendor-neutral — it governs paths and quotas only. Swap the underlying storage driver (local, S3, GCS) without changing governance logic.
