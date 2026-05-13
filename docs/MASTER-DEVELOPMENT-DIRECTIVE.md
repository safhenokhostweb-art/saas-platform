# Master Development Directive

> Source of truth for every coding task in both repositories. Adopted on ۱۴۰۵/۰۲/۲۲ (Wednesday, May 13, 2026). Every subsequent agent action must conform to this document. If the directive conflicts with anything in `.cursor/rules/` or `docs/PROJECT-GUIDE.md`, the directive wins.

## Project Vision

Build a modular SaaS Platform with two repositories:

* **saas-platform** (central platform)
* **customer-site** (tenant/customer website)

The system must be:

* Modular and scalable
* Secure and auditable
* Easy to deploy
* Easy to extend with new modules
* Safe for customer entitlements
* Backward compatible
* Maintainable for long-term growth

---

# Core Principles (Non-Negotiable)

1. **Customer entitlements must never be lost.** Purchased or granted modules must remain protected.
2. **No automatic revocation.** Any revocation must require explicit admin confirmation.
3. **Every important action must be audited.** Use activity logs and admin activity logs.
4. **Backward compatibility is mandatory.** Existing customer sites must continue working.
5. **Infrastructure before features.** Complete and stabilize the foundation before major feature development.
6. **One step at a time.** Analyze, implement, test, pause, and wait for approval.
7. **Future extensibility is a core requirement.** Adding a new module should require minimal effort.
8. **No destructive operations without approval.** Never run `migrate:fresh`, `db:wipe`, force deletions, or any data-destructive action unless explicitly authorized.

---

# Development Workflow Rules

## Before Any Code Change

1. Analyze the current state.
2. Produce an Analysis Report.
3. Explain risks and impact.
4. Wait for my explicit approval.

## During Implementation

1. Work on a dedicated branch.
2. Create small, focused commits.
3. Update CHANGELOG and PROJECT-GUIDE.
4. Add or update tests.
5. Preserve backward compatibility.

## After Implementation

1. Provide a Sanity Runbook.
2. Run or describe tests.
3. Pause and wait for approval.
4. Merge only with my explicit confirmation.

## Branching Rules

* Never work directly on `main`.
* Use dedicated branches (e.g., `phase5-wave5`).
* Use `git merge --no-ff`.
* Preserve all historical branches and tags.
* Never delete branches or tags unless explicitly instructed.

---

# Phase 1 — Core Infrastructure Stabilization

Complete and stabilize all foundational systems before major business feature development.

## Required Infrastructure

1. Licensing System
2. Module Entitlement Ledger
3. Module Marketplace API
4. Audit Logging
5. Admin Activity Logging
6. Safe Deployment Scripts
7. RBAC & Permissions
8. Module Retirement System
9. Rate Limiting & Captcha
10. Regression Test Suite
11. Staging/Production Deployment Workflow
12. Module Assignment Architecture

## Phase 1 Goal

Create a stable core where:

* Customer entitlements are protected.
* Revocations are manual and audited.
* Deployment is predictable.
* Testing is comprehensive.
* New modules can be added easily.

## Rule

Do not begin major business feature development until the infrastructure is stable and tested.

---

# Phase 2 — Existing Module Audit and Stabilization

After infrastructure is stable:

1. Audit each existing module individually.
2. Identify architecture, security, performance, and logic issues.
3. Fix bugs.
4. Add tests.
5. Update documentation.
6. Produce a final module health report.

## Modules Must Be Audited One by One

Examples:

* Blog
* Shop
* Forms
* Coupons
* Advertisements
* Messaging/WhatsApp
* Support
* CRM

---

# Phase 3 — Feature Development

After a module is stable:

1. Add new features requested by me.
2. Ensure each feature is:

   * Modular
   * Tested
   * Documented
   * Backward compatible
   * Secure

---

# Phase 4 — Future-Proof Module Architecture

The system must allow adding new modules with minimal effort.

## Adding a New Module Must Be Simple

A new module should require only:

1. Registering the module in the `modules` table.
2. Creating module-specific migrations.
3. Defining routes.
4. Creating controllers.
5. Creating views.
6. Defining permissions.
7. Defining entitlement rules.
8. Automatic marketplace visibility.
9. Assignment to customer sites.
10. Independent activation/deactivation.

## Core Requirement

The core system must not require significant refactoring when a new module is added.

---

# Phase 5 — Customer Module Assignment

Each module must support:

1. Publication in the central platform.
2. Visibility in the marketplace.
3. Purchase or manual activation.
4. Entitlement recording in the ledger.
5. Synchronization to customer sites.
6. Safe activation.
7. Explicit, audited revocation only.

---

# Recommended Development Order

Always follow this order:

1. Infrastructure
2. Module Stabilization
3. Feature Development
4. Future Extensibility
5. Customer Assignment

---

# Testing Requirements

Every significant change must include:

* Unit tests
* Feature tests
* Regression tests
* Manual sanity runbook

The regression suite should protect all critical behaviors introduced in previous waves.

---

# Documentation Requirements

For every approved change:

1. Update `docs/CHANGELOG.md`
2. Update `docs/PROJECT-GUIDE.md`
3. Document migrations, APIs, workflows, and risks

---

# Deployment Workflow

Development and deployment must follow this process:

1. Cursor develops locally (Laragon).
2. Changes are committed to Git.
3. Branches are pushed to GitHub.
4. Deploy to Staging.
5. Perform real testing.
6. After approval, merge to `main`.
7. Deploy to Production using deployment scripts.

---

# Module Audit Procedure

When I ask you to audit a module:

1. Analyze the module architecture.
2. Identify security issues.
3. Identify performance issues.
4. Identify logical and UX problems.
5. Check tests and missing coverage.
6. Propose improvements.
7. Wait for approval.
8. Implement approved fixes.
9. Re-test.
10. Report final status.

---

# Feature Development Procedure

When I request a new feature:

1. Analyze requirements.
2. Assess impact.
3. Propose architecture.
4. Wait for approval.
5. Implement on a dedicated branch.
6. Add tests.
7. Update documentation.
8. Pause for approval before merge.

---

# Safety Rules

Never do the following without explicit approval:

* `php artisan migrate:fresh`
* `php artisan db:wipe`
* Dropping production data
* Force deleting customer entitlements
* Automatic module revocation
* Force pushing over important branches
* Deleting branches or tags

---

# Final Project Goal

Create a SaaS Platform where:

* Infrastructure is robust and secure.
* Existing modules are stable.
* New features can be added safely.
* New modules can be added easily.
* Customer entitlements are permanently protected.
* Deployment is simple and reliable.
* Long-term growth does not require major refactoring.

---

# Current Working Mode

At all times, follow this sequence:

1. Complete and stabilize infrastructure.
2. Audit and fix existing modules one by one.
3. Add requested features.
4. Preserve future extensibility.
5. Keep customer assignment secure and auditable.

---

# Mandatory Operating Rule

Before starting any new task:

1. Read this file.
2. Analyze the requested task.
3. Produce an Analysis Report.
4. Wait for my explicit approval.

Do not make code changes, migrations, commits, merges, or deployments until I approve.

---

# Immediate Instruction

Use this Master Development Directive as the governing architecture and workflow for the entire project.

Your responsibilities are:

1. Finish and stabilize the infrastructure.
2. Audit and fix each existing module individually.
3. Add requested features only after stabilization.
4. Ensure adding future modules remains simple and low-risk.
5. Pause after every wave or sub-wave and wait for my approval.
