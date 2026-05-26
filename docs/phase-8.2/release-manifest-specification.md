# Release Manifest Specification

**Phase 8.2 — Immutable Release Records**

---

## Location

```
storage/app/releases/{deployment-uuid}.json
```

Manifests are immutable after creation. Never modify an existing manifest file.

---

## Schema

```json
{
  "deployment_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "environment": "production",
  "git_commit": "a3f8c1d92e7b4...",
  "git_branch": "main",
  "package_versions": {
    "laravel/framework": "v10.48.0",
    "php": "8.2.1"
  },
  "module_versions": {
    "forms": "2.1.0",
    "tickets": "1.8.3"
  },
  "migration_batch": 47,
  "asset_checksum": "sha256:9f86d081...",
  "php_version": "8.2.1",
  "laravel_version": "10.48.0",
  "platform_version": "1.0.0",
  "deployed_by": "hostname:pid",
  "released_at": "2026-05-26T12:00:00+00:00",
  "rollback_target": "previous-uuid",
  "checksum": "sha256:computed-over-all-other-fields"
}
```

---

## Checksum Integrity

The `checksum` field is a SHA-256 hash of the entire manifest JSON excluding the `checksum` field itself.

**Verification:**
```bash
php artisan deployment:status --json | jq '.latest_release'
# Check: checksum field must match re-computed hash
```

Or programmatically via `ReleaseManifestValidator::validateFromFile(uuid)`.

---

## Diffing Releases

```php
$diff = $manifestManager->diff($oldUuid, $newUuid);
// Returns: module_version_changes, migration_batch_change, git_commit_change
```

---

## Manifest Lifecycle

1. **Created:** at end of successful deployment (in `finalized` phase)
2. **Referenced:** by `deployment:status`, `deployment:rollback`, health endpoints
3. **Validated:** by `ReleaseManifestValidator` at each rollback
4. **Retained:** permanently — do not delete manifests (rollback requires them)

---

## Retention Policy

Keep all manifests in production. For storage efficiency, archive manifests older than 90 days to cold storage. Never delete the last 5 manifests.
