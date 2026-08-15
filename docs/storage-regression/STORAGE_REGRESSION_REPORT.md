# Storage Regression Audit & Minimal Hotfix

**Scope:** `saas-platform` + `customer-site`
**Trigger:** MediaStorage / Storage Abstraction refactor
**Refactor commits:** `saas-platform@1652077` ("SimpleDeploy: به‌روزرسانی خودکار 2026-08-11 14:25"), `customer-site@ef7d94a` ("SimpleDeploy: به‌روزرسانی خودکار 2026-08-11 13:58")

---

## 1. Audit scope

Both auto-deploy commits bundle the MediaStorage refactor together with unrelated feature work
(Motion Bespoke Commercialization in saas-platform; SEO-advanced/CRM cleanup in customer-site).
This audit isolates only the storage-relevant files from each commit:

**saas-platform** — `app/Services/Infrastructure/MediaStorage.php` (new), `config/filesystems.php`,
`app/Http/Controllers/ProfileController.php`, `Admin/AdminProfileController.php`, `Admin/UserController.php`,
`Admin/AppReleaseController.php`, `Admin/SecureAttachmentController.php`, `Admin/ThemeMediaController.php`,
`Api/Mobile/MobileTicketController.php`, `Models/Profile.php`, `Services/Seo/SitemapService.php`,
`resources/views/{profile/edit,admin/profile/index,admin/users/edit,admin/users/_table,partials/navbar}.blade.php`, `.env.example`.

**customer-site** — `app/Services/MediaStorage.php` (new), `config/filesystems.php`,
`app/Helpers/ImageHelper.php`, `Http/Controllers/Admin/ImageUploadController.php`,
`Http/Controllers/Admin/SupportController.php`, `Services/SmartImageUploadService.php`,
`Services/TenantSupportRuntime/TenantAttachmentService.php`, `.env.example`.

Every remaining `Storage::disk()` / `Storage::put*()` / `->move()` / `asset()` / `url()` call site in both
`app/` trees was enumerated (grep sweep, ~120 matches) and classified per call site — see §10.

---

## 2–9. Findings by area

### Profile / avatar upload — both repos

Full trace (Form → Request → Validation → Controller → MediaStorage → Disk → DB → URL → Browser) was
exercised **live** against real files and a real MySQL test DB for every code path (`ProfileController`,
`AdminProfileController`, `UserController` in saas-platform; `ProfileAvatarController`,
`AdminProfileController` in customer-site).

**Result under the shipped default configuration (`MEDIA_DISK`/`UPLOAD_DISK` unset in both `.env` files,
confirmed on this environment):** disk resolution, physical file writes, and generated URLs are **byte-for-byte
identical** to pre-refactor behavior in every flow tested. This was verified by:

- Live `StorageManager::storeUploadedFile()` writes + `Storage::disk('uploads')->url()` comparisons.
- A full HTTP round-trip (`PUT /profile`, `PUT /admin/profile`) with a real `UploadedFile`, asserting the
  DB column and the resulting physical file.
- Comparing the new `ImageHelper::url()` / `MediaStorage::url()` output against the exact `asset('storage/'.…)`
  / `asset('uploads/'.…)` calls it replaced, for both legacy and new-style stored paths.

So the refactor did **not**, by itself, break avatar display or upload under default config. Two real defects
were found, however — one in the abstraction, one in the surrounding UX — and both are fixed below.

#### CONFIRMED REGRESSION — `MediaStorage::diskNameFor()` kind coupling (saas-platform)

**File:** `app/Services/Infrastructure/MediaStorage.php`
**Classification:** C) Broken after refactor

```php
// before
if (in_array($mode, ['public', 'uploads'], true)) {
    return $kind === 'uploads' ? 'uploads' : $mode;   // <-- returns $mode, not 'public'
}
```

`config/filesystems.php` and `.env.example` (added by this same refactor) explicitly document `MEDIA_DISK` /
`UPLOAD_DISK` accepting the value `uploads` as a legal **local** setting. If an operator sets it (e.g. to
consolidate avatar + theme media under one name), `diskNameFor('public')` — used by `ThemeMediaController`
and `SitemapService` — silently returned the `uploads` disk instead of `public`. Existing theme images and
`sitemap.xml`, which physically live on the `public` disk, would 404 for every request, with no error anywhere
(a `Storage::disk('uploads')->url()` call happily returns a URL for a file that isn't there).

This is a genuine, reachable latent bug in the new abstraction — the customer-site equivalent
(`app/Services/MediaStorage.php`) does **not** have it (its `legacy`/`uploads` branches are hardcoded rather
than deriving from `$mode`, unit-tested by the pre-existing `MediaStorageTest`).

**Fix applied** (one-line, preserves the documented default exactly):

```php
if (in_array($mode, ['public', 'uploads'], true)) {
    return $kind === 'uploads' ? 'uploads' : 'public';
}
```

#### CONFIRMED FIX (not a regression) — `TenantAttachmentService` disk (customer-site)

**File:** `app/Services/TenantSupportRuntime/TenantAttachmentService.php`

Before the refactor, this service stored support attachments on `config('filesystems.support_disk', 'local')`
while `SupportController::download()` and `SecureAttachmentController` already checked
`Storage::disk('private')` — a disk that **had no config entry at all** (`config/filesystems.php` had no
`'private'` key), so every download would have thrown *"Disk `[private]` does not have a configured driver."*
The refactor added the missing `'private'` disk definition (root `storage_path('app/private')`, identical to
`'local'`) and switched `TenantAttachmentService` to `$media->diskNameFor('private')` → `'private'`. Net effect:
uploads and downloads now agree on the same disk, and — because `'private'` and `'local'` share the exact same
root — attachments stored before the refactor remain reachable with no migration. This is a fix the refactor
correctly shipped, not a regression; flagged here only because §5/§10 required auditing it.

#### CONFIRMED UX DEFECT — silent avatar-upload failures ("no visible error"), both repos

**Classification:** pre-existing, exposed by (not caused by) the refactor; directly matches the reported symptom.

Independent of any storage-disk issue, three concrete gaps make an avatar upload failure invisible to the user:

1. **saas-platform** `ProfileController`/`AdminProfileController`/`UserController::update()` already wrapped the
   upload in `try { … } catch (\Throwable $e) { Log::error(...); }` — logs the error, then falls through to a
   **generic "success" response** with no mention that the avatar specifically failed.
2. **customer-site** `AdminProfileController::update()` had `if ($file->isValid()) { … }` with **no `else`** —
   an invalid/partial upload (e.g. PHP `upload_max_filesize` exceeded) silently did nothing and still returned
   `{"success": true, ...}`.
3. **customer-site** `admin/profile/index.blade.php`'s Alpine `save()` handler only had `if (data.success) { … }`
   — there was no branch at all for a non-2xx response or a `false` `success` flag, so even a real validation
   error from the server was swallowed client-side.
4. **customer-site** `ProfileAvatarController::update()` had no `try/catch` around `$file->move()`; a real
   filesystem error (permissions, disk full) surfaced as Laravel's raw 500 debug/error page rather than the
   app's normal flash-message UI.

None of these four are disk-resolution bugs — they are why a *real* storage failure (this one or a future one,
local or after a `MEDIA_DISK` migration to object storage) would look exactly like "nothing happened, no
error," which is the literal reported symptom. Fixed per §11.

### Blog / content media (customer-site)

`Post::featured_image` / `gallery_images` are rendered exclusively through `App\Helpers\ImageHelper::url()`
(`themes/default/blog/{show,index}.blade.php`), which **was** touched by the refactor. Traced both conventions
live against real files already on disk in this environment:

| DB path convention | Physical location | Disk (`kind`) | Old code | New code | Match? |
|---|---|---|---|---|---|
| `uploads/featured/2026/02/x.png` (legacy) | `storage/app/public/uploads/featured/…` | `legacy` → `public` | `asset('storage/'.$path)` | `Storage::disk('public')->url($path)` | ✅ identical URL |
| `featured/2026/05/x.jpg` (new, `SmartImageUploadService`) | `public/uploads/featured/…` | `uploads` | `asset('uploads/'.$path)` | `Storage::disk('uploads')->url($path)` | ✅ identical URL |

No regression found for blog images under the shipped default config — confirmed with real files already
present in this environment (`storage/app/public/uploads/featured/2026/02/*.png` and
`public/uploads/featured/2026/05/*.{jpg,png}`) and with new regression tests (§9). `public/storage` is a valid
symlink to `storage/app/public` in this environment; if it is **missing on the production host**, legacy
images would 404 regardless of the refactor — see §"Required production actions".

### Theme media (saas-platform: `ThemeMediaController`; customer-site: `ThemeDesignMediaController`)

saas-platform's `ThemeMediaController` was migrated to `$this->media->diskNameFor('public')`; under default
config this still resolves to `'public'` — unaffected before the fix in §"Profile", and *actually fixed by it*
for the `MEDIA_DISK=uploads` case. Upload, list, and delete all round-trip correctly.

customer-site's `ThemeDesignMediaController` was **not** touched by the refactor at all — it still hardcodes
`private const DISK = 'public'`. Functionally fine (classification B — legacy but compatible / D — should use
MediaStorage), flagged as a follow-up, out of scope for this hotfix per the "do not create another storage
abstraction / minimal diff" constraint.

### Attachments (both repos)

- saas-platform `SecureAttachmentController`, `AppReleaseController`: migrated to
  `$this->media->diskNameFor('private')`; identical to the previous hardcoded `'private'`/`'download-disk'`
  literals under default config. Authorization checks (tenant/site ownership) are untouched by the refactor.
- customer-site `SupportController::download()` + `TenantAttachmentService`: see the confirmed-fix finding
  above — this pairing is *more* correct after the refactor than before.
- Per-row `disk` columns (`SupportMessageAttachment.disk`, `PlatformAttachment.disk` in saas-platform) mean
  historical attachment rows keep referencing whatever disk name they were written with; since `'local'` and
  `'private'` (saas) / `'local'` and `'private'` (customer-site) share the same physical root, old rows resolve
  correctly with **no migration**.

### Sitemap / public media (saas-platform: `SitemapService`)

`Storage::disk('public')->put('sitemap.xml', …)` → `Storage::disk($this->media->diskNameFor('public'))->put(...)`.
Identical disk under default config; would have inherited the §"Profile" `MEDIA_DISK=uploads` bug (now fixed).

### Mobile / API media (both repos)

saas-platform `MobileTicketController::uploadAttachment()` migrated to
`$this->media->diskNameFor('private')` — unaffected (same reasoning as attachments, above).
customer-site's mobile ticket controllers (`CustomerMobileTicketController`, `SupportApiClient`,
`Admin/UnifiedSupportController`, `Admin/UserTicketController`) were **not** touched by the refactor; they use
raw `public_path('uploads/…')` + `$file->move()` self-consistently (classification B).

### Motion Graphics (saas-platform)

Confirmed **not affected** — no `Storage::`, `MediaStorage`, or file-upload code exists anywhere under
`app/Modules/MotionGraphics`. Left untouched per the task's explicit instruction.

### Environment / disk resolution (both repos, current actual `.env`)

| | saas-platform | customer-site |
|---|---|---|
| `MEDIA_DISK` | unset | unset |
| `UPLOAD_DISK` | unset | unset |
| `FILESYSTEM_DISK` | `local` | `local` |
| Effective `upload_disk` mode | `public` (fallback default) | `uploads` (fallback default) |
| `public` disk root | `storage/app/public` | `storage/app/public` |
| `uploads` disk root | `public/uploads` | `public/uploads` |
| `private`/`local` disk root | `storage/app/private` (both names, same root) | `storage/app/private` (both names, same root) |
| `public/storage` symlink | present, valid | present, valid |

Default configuration in both repos preserves pre-refactor behavior exactly (verified live, not just by
reading code). The one place default-preserving behavior was *not* guaranteed — saas-platform's
`diskNameFor('public')` under an explicit `MEDIA_DISK=uploads` — is fixed in §11.

---

## 10. Full call-site classification (representative sample; ~120 sites audited)

| File | Pattern | Classification |
|---|---|---|
| `saas-platform: MediaStorage.php` | `diskNameFor()` | C → fixed (see above) |
| `saas-platform: ProfileController/AdminProfileController/UserController` | `$media->diskNameFor('uploads')` | A) correct, now also error-visible |
| `saas-platform: ThemeMediaController/SitemapService/SecureAttachmentController/AppReleaseController/MobileTicketController` | `$media->diskNameFor(...)` | A) correct |
| `saas-platform: ResellerBranding.php`, `PageController.php` | `Storage::disk(config('filesystems.upload_disk','public'))` | D) should use MediaStorage — not touched by refactor, functionally fine under default config, shares the same coupling class as the fixed bug if `UPLOAD_DISK=uploads` is ever set. Left as-is (minimal diff; no MediaStorage call site to break). |
| `saas-platform: PlatformInstallerController`, `StorageManager::storeUploadedFile` | raw `$file->move()` to a temp path, then `Storage::disk()->writeStream()` | A) intentional — PHP 8.4/Flysystem `getRealPath()` workaround, documented in `StorageManager`'s own docblock |
| `customer-site: ImageHelper.php` | `$media->url($path,'legacy'|'uploads')` | A) correct (verified, no coupling bug — kind is hardcoded per branch, not derived from `$mode`) |
| `customer-site: SmartImageUploadService.php` | `$this->media->disk('uploads')` | A) correct |
| `customer-site: TenantAttachmentService.php` | `$media->diskNameFor('private')` | A) now correct — was a pre-existing broken reference (see confirmed-fix finding) |
| `customer-site: ProfileAvatarController.php`, `Admin/AdminProfileController.php` | raw `$file->move()` / `public_path('uploads/…')` | B) legacy but compatible — bypasses MediaStorage entirely, not touched by refactor, self-consistent. Hardening applied for visible errors only (§11); not migrated to MediaStorage to keep the diff minimal and because moving it is exactly the kind of "redesign MediaStorage" scope the task prohibits. |
| `customer-site: ThemeDesignMediaController.php` | hardcoded `DISK = 'public'` | D) should use MediaStorage, out of scope |
| `customer-site: SettingController`, `SubscriptionPlanController`, `NotificationController` | hardcoded `Storage::disk('public')`/`Storage::disk('uploads')` | B) legacy but compatible, not touched by refactor |
| `saas-platform: ArvanBackupService`, `SiteArchiveService`, `RemoveRecoveryMetadataStage` | `Storage::disk('arvan_backup')`/`Storage::disk('local')` | E) out of scope by design — backup/archive architecture, explicitly excluded from this task |
| `customer-site: BackupDatabaseCommand` | `Storage::disk('arvan_backup')` | E) out of scope by design |

---

## 11. Minimal hotfix applied

All changes reuse `MediaStorage`/`StorageManager`, preserve existing DB paths and files, and add no new
storage abstraction.

**saas-platform**
- `app/Services/Infrastructure/MediaStorage.php` — one-line fix: `diskNameFor()` no longer lets the `'public'`
  kind fall through to `$mode` when `$mode === 'uploads'`.
- `app/Http/Controllers/ProfileController.php` — avatar-upload failures (`\Throwable` or invalid file) now
  redirect with `withErrors(['avatar' => …])` while still persisting the rest of the profile form.
- `app/Http/Controllers/Admin/AdminProfileController.php` — same, plus a `avatar_error` key in the JSON
  response for the AJAX form, and a fallback `withErrors()` for the non-AJAX path.
- `app/Http/Controllers/Admin/UserController.php` — `avatar_error` key added to the JSON response.
- `resources/views/admin/profile/index.blade.php` — Alpine `save()` now branches on `data.avatar_error` /
  `!data.success` / network failure and shows a visible red banner (previously: only a bare
  `if (data.success)`, nothing otherwise).
- `resources/views/admin/users/edit.blade.php` — `avatar_error` surfaced through the existing (already
  rendered) `serverErrors` mechanism, halting the redirect so the message is seen.

**customer-site**
- `app/Http/Controllers/Account/ProfileAvatarController.php` — `mkdir()`/`$file->move()` wrapped in
  `try/catch`, converting an uncaught filesystem exception (raw 500 page) into a visible
  `withErrors(['avatar' => …])` flash, rendered by the already-present `account.layout` error block.
- `app/Http/Controllers/Admin/AdminProfileController.php` — added the missing `else` branch for
  `$file->isValid() === false`, wrapped the move in `try/catch`, and added `avatar_error` to the JSON response
  (plus a `withErrors()` fallback for non-AJAX).
- `resources/views/admin/profile/index.blade.php` — same Alpine hardening as the saas-platform admin profile
  view.

No database migration, no file movement, no changes to Tenant Context, Deployment, Backup/Archive, or Motion
Graphics.

---

## 12. Tests

**New:**
- `saas-platform: tests/Unit/Services/Infrastructure/MediaStorageTest.php` — added
  `test_explicit_local_uploads_mode_does_not_move_public_kind_off_the_public_disk` (fails on the pre-fix code,
  passes after).
- `saas-platform: tests/Feature/Infrastructure/ProfileAvatarStorageRegressionTest.php` — legacy avatar
  resolves; new upload succeeds + updates DB path + physical file; upload failure is visible via
  `withErrors('avatar')` while other fields still save; admin JSON response reports `avatar_error`.
- `customer-site: tests/Feature/Account/ProfileAvatarStorageRegressionTest.php` — new avatar upload succeeds
  (asserted against the real `public/uploads` path, since `ProfileAvatarController` bypasses the `Storage`
  facade by design); invalid file produces a visible session error instead of a silent no-op; legacy blog image
  path still resolves through `ImageHelper`; new blog image path resolves through the `uploads` disk.

**Existing (unchanged), re-verified:**
- `saas-platform: tests/Unit/Services/Infrastructure/MediaStorageTest.php` (pre-existing 6 tests) — pass.
- `saas-platform: tests/Feature/Infrastructure/StorageSecurityTest.php` — 12 pre-existing failures, all in
  `test_dangerous_logo_paths_are_rejected` (`PlatformSettings::normalizeStoragePath`), a file I did not touch;
  confirmed pre-existing and unrelated to the storage refactor or this hotfix (Windows-path-handling issue,
  outside this task's scope).
- `customer-site: tests/Unit/Helpers/ImageHelperTest.php`, `tests/Unit/Services/MediaStorageTest.php` — pass.
- `customer-site: tests/Unit` (95 tests) — all pass.

**Run:**
```
saas-platform:  php vendor/bin/phpunit tests/Unit            → 881 tests, 6 errors, 1 failure (all pre-existing, CRM test-DB tables missing — unrelated to storage)
saas-platform:  php vendor/bin/phpunit tests/Feature/Infrastructure → 64 tests, 12 failures (all pre-existing, PlatformSettings — unrelated to storage)
customer-site:  php vendor/bin/phpunit tests/Unit             → 95 tests, all pass
customer-site:  php vendor/bin/phpunit tests/Feature/Account  → 4 tests, all pass
```

**Pre-existing, unrelated failures found and explicitly *not* fixed (out of scope):**
1. saas-platform `database/factories/UserFactory.php` still inserts an `email` column that migration
   `2026_05_30_000001_remove_email_from_users_and_password_reset_tokens` dropped from `users` — breaks
   `User::factory()->create()` in **every** feature test that uses it (confirmed on an unrelated pre-existing
   test, `UserSecurityApiTest`, 26/26 failures). My new feature tests build `User` directly to avoid this
   shared, unrelated defect rather than touching the factory.
2. saas-platform `tests/Unit/Modules/Crm/*` — `crm_contacts` and related tables don't exist in the
   `saas_platform_test` database (migration/test-DB drift, unrelated to storage).
3. saas-platform `StorageSecurityTest::test_dangerous_logo_paths_are_rejected` — `PlatformSettings::normalizeStoragePath`
   accepts absolute paths it should reject, on this Windows environment. Not part of the storage-refactor diff.

---

## 13. Remaining risks

- **`MEDIA_DISK=uploads` on saas-platform** is now safe (fixed), but has not been exercised end-to-end against
  a live deployment — only via unit test with `Config::set()`.
- customer-site's `ThemeDesignMediaController`, and several controllers listed as classification B/D in §10,
  still bypass `MediaStorage`. They are functionally correct today but will need updating *before* any future
  `MEDIA_DISK=arvan`/`s3` migration, since they will keep resolving to the local `public`/`uploads` disks
  regardless of that env var.
- customer-site's `AdminProfileController` and `ProfileAvatarController` avatars use two different stored-path
  conventions (`uploads/admin-avatars/…` with prefix vs. `avatar/2026/05/…` without). Each renders correctly
  through its own matching URL-generation code today, but if a future change ever routes an admin-avatar path
  through `ImageHelper::url()` (which treats *any* `uploads/`-prefixed path as the legacy/`public`-disk
  convention), it would resolve to the wrong disk. Flagged as a "potential issue," not a confirmed regression —
  no code path currently does this.

---

## Required production actions

None are required to preserve current behavior — the shipped defaults (`MEDIA_DISK`/`UPLOAD_DISK` unset in
both `.env.example` templates) reproduce pre-refactor behavior exactly, and this hotfix does not change any
default. If production `.env` differs from the templates checked into these repos:

1. Confirm `public/storage` (customer-site) / `storage/app/public` symlink exists (`php artisan storage:link`)
   — legacy blog images depend on it. If it does not exist on the production host, that is the operational fix
   for a "blog images not displayed" report — not an application-code change.
2. If production's saas-platform `.env` sets `UPLOAD_DISK=uploads` (rather than the default `public`), the
   `diskNameFor()` fix in this hotfix directly resolves a live regression against theme media / sitemap.xml —
   deploy this fix and no further action is needed (no migration, no file movement).
3. If `MEDIA_DISK` is ever pointed at `arvan`/`s3` in either repo, existing local files are **not**
   auto-migrated (by design, documented in `MediaStorage`'s own docblock) — that remains a deliberate,
   pre-existing limitation, not something this hotfix changes or is asked to fix.
