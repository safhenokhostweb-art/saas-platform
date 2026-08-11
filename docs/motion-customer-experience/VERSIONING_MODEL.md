# Versioning Model (customer-facing)

Unchanged from Phase X — this phase adds no versioning logic, only a customer-safe display of what already exists.

Admin still manages the full version tree (`MotionGraphicVersion`, module-owned, immutable, non-deletable history — Phase 1-2). A customer's page shows exactly **one** number: `published_version` for whichever version is currently bound by their site's `MotionBespokeAssignment.active_version` — resolved through `MotionAssetReadContract::findByCanonicalId($assetId, $activeVersion)`, which returns `null` for anything not `approved`/`published`. There is no code path in `MotionGraphicsController` or its view that can reach a draft, review, or internal version — the read contract makes it structurally unreachable, not just hidden by the UI.

Publishing a new version, and whether existing assignments move to it, remains **entirely** Phase X's `MotionBespokeAssignmentService::assign()` — a deliberate, explicit Platform-Admin action per commission/site. Nothing in this phase calls it automatically. The task's "existing assigned sites continue using their current published version by default" is exactly Phase X's existing behavior (an assignment's `active_version` never changes except through an explicit `assign()`/`rollback()` call) — no new "bulk upgrade assigned sites" capability was requested or built this phase.
