# Module Release Changelog Convention

**Status:** Accepted
**Date:** 2026-07-10
**Phase:** Phase 5 / Commit 12-M
**Related:** [module-version-authority-adr.md](module-version-authority-adr.md)

---

## Purpose

`module_releases.changelog` is the field surfaced to customer sites via
`UpdateController::moduleHistory()` (audited in Commit 12-L). The column has
existed since the table was created, but no release producer ever populated
it and no convention existed for what belongs in it. This document defines
that convention for whoever runs `module:release --changelog=`. It is
guidance for a human operator, not a database or API contract change.

## Audience

Changelog text entered here is customer-facing. It reaches tenant site
administrators through the public update API (`/api/updates/module-history`).
Write for that audience — not for other platform developers, and not as an
internal engineering log.

## Allowed

- New features
- Performance improvements
- General bug fixes, described in outcome terms a customer would recognize
- Any change a customer can actually observe in their own site

## Forbidden

- File paths
- Class or namespace names
- Developer names
- Internal ticket/issue references (e.g. "fixes TICKET-123")
- Security vulnerability details — even a generic "fixed SQL injection in X"
  is a roadmap for attackers targeting sites still on the old version.
  Describe security fixes only in generic terms ("security hardening"),
  never with specifics.
- Internal architecture details

## Enforcement

This is intentionally light. `module:release --changelog=` only enforces a
practical length bound (500 characters) to catch obvious mistakes, such as
pasting an entire diff or log as the changelog. It does not attempt to
semantically detect forbidden content — reliably identifying a file path or
ticket number in free text is out of scope for this commit and prone to
false positives/negatives. Reviewing changelog content against the rules
above is the responsibility of whoever runs the release.

## Producer coverage

| Producer | Populates `changelog`? |
|---|---|
| `module:release --changelog=` | Yes — optional, operator-supplied. |
| `module:release-backfill` | No, deliberately (Commit 12-M decision). Backfilled releases have no real historical changelog to report. A generated placeholder (e.g. "backfilled release") would itself violate this convention by leaking internal process detail, and could mislead a tenant into thinking they're reading a real release note. `changelog` stays `null` for backfilled rows unless a future, separate mechanism supplies real per-module historical text.
