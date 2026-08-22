# SaaS Platform artifact rollback runbook

Rollback is performed only through Deployment System using a previously
validated artifact and its recorded SHA-256 checksum. Never check out Git,
install Composer dependencies, build assets, create tags/releases, or push from
production.

## Before activation

- retain the currently active release within the configured retention window;
- record the prior artifact ID, commit SHA, checksum, and health result;
- take and verify a database backup;
- review every pending migration for backward compatibility;
- stop the release if the previous code cannot run against the post-migration
  schema and no verified database recovery path exists.

## Failed release

1. Keep maintenance/drain state active.
2. Ask Deployment System to activate the last known-good artifact.
3. Verify the selected artifact checksum and release manifest before swap.
4. Restore persistent `.env`, `storage`, and upload references unchanged.
5. Run `php artisan optimize` against the restored code.
6. Run `php artisan queue:restart` and verify worker processes return.
7. Require HTTP 200 from `/health/ready` before restoring traffic.
8. Record the failed and restored artifact IDs, SHAs, checksums, migration state,
   and operator in the Deployment System audit trail.

## Database compatibility

Code rollback does not automatically reverse migrations. Prefer additive,
expand/contract migrations that allow the previous release to continue running.
Do not run `migrate:rollback` automatically: a `down()` method can discard data
or be incompatible with later writes.

When schema recovery is required, keep traffic drained and restore the verified
pre-release database backup as a separately authorized recovery operation. Then
activate the matching artifact, optimize, restart queues, and repeat health
validation.

## Rollback failure

If the prior artifact, checksum, persistent targets, or compatible database
state cannot be verified, do not improvise with production Git. Keep the
service in maintenance, preserve all evidence and backups, and mark the
Deployment System operation failed for controlled recovery.
