# SaaS Platform artifact deployment runbook

Deployment System is the only production release authority. Production is a
runtime target, never a source repository or build machine.

## Required flow

```text
Local -> committed SHA -> GitHub -> Local Agent isolated build
-> complete artifact -> checksum -> Deployment System -> production
```

The Local Agent builds the exact GitHub SHA and includes Composer dependencies,
Vite output, and `.deployment/release-manifest.json`. Production must not run
Git, Composer, npm, create a release, or push any ref.

## Pre-release gates

1. Confirm the local tree is clean and local HEAD equals GitHub `main`.
2. Run `php scripts/release-compatibility.php` and its self-test.
3. Require successful Composer platform checks for the Local Agent PHP runtime.
4. Require `vendor/autoload.php`, Laravel bootstrap files,
   `public/build/manifest.json`, and the Deployment System release manifest in
   the sealed artifact.
5. Take and verify a database backup before migrations.
6. Confirm a previously validated artifact and its checksum are available as
   the rollback target.

## Persistent state

The artifact must not own `.env`, `storage`, `public/uploads`, or
`public/storage`. Prefer Deployment System persistent references for `storage`,
`public/uploads`, and `public/storage`, with `.env` as the only copy-preserved
path. The persistent root and every target must exist before activation, and
preserve/persistent lists must not overlap.

Until that layout is provisioned, explicitly preserve `.env`, `storage`,
`public/uploads`, and `public/storage`. Never preserve `vendor` or
`public/build`.

## Activation sequence

Deployment System must:

1. verify the artifact checksum and completeness gate in staging;
2. drain workers or enter maintenance as configured;
3. atomically swap the staged release while retaining the prior release;
4. run `php artisan migrate --force`;
5. run `php artisan optimize`;
6. run `php artisan queue:restart` and verify all managed worker groups return;
7. require HTTP 200 from `/health/ready`;
8. record the deployment SHA, artifact checksum, status, and rollback target.

`storage` and `bootstrap/cache` must be writable by the PHP and worker users.
The web server must be permitted to follow the configured persistent links.

Do not use `/health/release` as the only Deployment System gate unless the
application-level runtime manifest has also been finalized; `/health/ready` is
the runtime dependency readiness probe.

No step in this runbook authorizes automatic deployment.
