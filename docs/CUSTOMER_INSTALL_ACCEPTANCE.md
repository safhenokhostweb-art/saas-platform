# Customer Installation Admin Center — Acceptance Test Plan
## PHASE-003B Acceptance Criteria

**Status:** READY FOR TESTING  
**Date:** 2026-06-27  
**Environment:** Production (87.107.12.81:9011)

---

## Pre-conditions

- [ ] PHASE-003A is certified and deployed
- [ ] All 5 PHASE-003A migrations applied to production DB
- [ ] At least one `CustomerInstallationPackage` registered (`php artisan install:register-package`)
- [ ] A reachable SSH test server available (or `FakeRemoteDriver` mode active)
- [ ] Admin user account exists with dashboard access
- [ ] Redis running on production

---

## AT-001 — Dashboard Load

**Scenario:** Admin opens the Customer Installation Dashboard

**Steps:**
1. Navigate to `/admin/infrastructure/customer-deployment/`

**Expected:**
- [ ] Page loads without PHP error
- [ ] 6 stat cards visible: Active, Queued, Complete, Failed, Servers, Packages
- [ ] "استقرار مشتریان" appears in sidebar under "زیرساخت / DevOps" group
- [ ] Sidebar link is active/highlighted
- [ ] Quick action buttons present (New Installation, Servers, History, Packages)

---

## AT-002 — Add Remote Server

**Scenario:** Admin registers an SSH server using key authentication

**Steps:**
1. Click "سرور جدید" or navigate to `/admin/infrastructure/customer-deployment/servers/create`
2. Fill in: Label="تست آلمان", Host="test.example.com", Port=22, Username="root"
3. Select auth method: Key
4. Paste a valid private key (PEM format) into the textarea
5. Submit form

**Expected:**
- [ ] Form validates required fields (label, host, port, username, key)
- [ ] On success: redirects to server list with "سرور با موفقیت اضافه شد" message
- [ ] Server appears in list with correct host/port/username
- [ ] Private key is **not** displayed anywhere in the response
- [ ] Database: `private_key_encrypted` column contains encrypted (non-readable) value

---

## AT-003 — Test Server Connection

**Scenario:** Admin tests SSH connectivity to a registered server

**Steps:**
1. Navigate to server list
2. Click the test (checkmark) button for the server added in AT-002

**Expected:**
- [ ] Button shows spinner while waiting
- [ ] Result row appears below server row
- [ ] On success: shows "✓ اتصال موفق" with PHP version, disk free, fingerprint
- [ ] On failure: shows "✗ اتصال ناموفق" with error message
- [ ] No SSH credentials appear in the result HTML

---

## AT-004 — Edit Server (Credential Preservation)

**Scenario:** Admin edits server label without changing credentials

**Steps:**
1. Navigate to edit for the server from AT-002
2. Change label to "تست آلمان ۲"
3. Leave private key textarea empty
4. Submit form

**Expected:**
- [ ] Redirects to server list with success message
- [ ] Label updated to "تست آلمان ۲"
- [ ] `private_key_encrypted` in DB: unchanged (not overwritten with null)
- [ ] Server still connects (credentials preserved)

---

## AT-005 — Installation Wizard (All 8 Steps)

**Scenario:** Admin runs through complete 8-step installation wizard

**Steps:**
1. Navigate to `/admin/infrastructure/customer-deployment/wizard`
2. Step 1: Fill customer info (company="شرکت آزمون", owner="مدیر تست", email="test@example.com")
3. Step 2: Select the server from AT-002
4. Step 3: Enter domain="test.example.com", select MODE_FRESH
5. Step 4: Keep defaults (backup_policy=none, ssl=false)
6. Step 5: Select Professional license
7. Step 6: (placeholder, next)
8. Step 7: Check "آموزش" module
9. Step 8: Review and submit

**Expected:**
- [ ] Each step shows without page reload (x-show transitions work)
- [ ] "بعدی" disabled if required fields empty (step validation)
- [ ] Step indicators show done state (green) for completed steps
- [ ] Step 8 shows all selected data in review table
- [ ] Submit creates `CustomerInstallation` record
- [ ] Redirects to installation detail page
- [ ] `CustomerInstallationPipelineJob` dispatched to `customer-install` queue
- [ ] Installation status = `pending` initially

---

## AT-006 — Real-time Installation Monitor

**Scenario:** Installation runs and admin watches live progress via SSE

**Steps:**
1. With a running installation, open its detail page
2. Observe the live progress section

**Expected:**
- [ ] `<div id="live-progress">` visible and SSE auto-connects
- [ ] Browser DevTools Network tab shows `/progress` as EventStream type
- [ ] Progress events update the progress bar percentage
- [ ] Current stage label updates as pipeline advances
- [ ] Status badge updates via `status` events
- [ ] On completion: "done" event closes SSE, page reloads after 2 seconds
- [ ] No JavaScript console errors

---

## AT-007 — Installation History

**Scenario:** Admin views and filters installation history

**Steps:**
1. Navigate to `/admin/infrastructure/customer-deployment/history`
2. Apply filter: Status = "موفق"
3. Apply filter: search = "آزمون"

**Expected:**
- [ ] History table shows all installations
- [ ] Status filter shows only matching records
- [ ] Search filter matches company/domain/email
- [ ] Date columns correct (created_at, duration)
- [ ] "جزئیات" link navigates to correct installation detail

---

## AT-008 — Installation Detail View

**Scenario:** Admin inspects a completed installation

**Steps:**
1. Navigate to a complete installation detail

**Expected:**
- [ ] Company name and status badge visible in header
- [ ] Log timeline shows all events in chronological order
- [ ] Dot colors: blue=started, green=completed, red=failed
- [ ] Right column: stage list shows all 20 stages with correct state (done/pending)
- [ ] No "Live Progress" or "Rollback" buttons (completed status)
- [ ] All metadata correct (server, domain, mode, license, modules)

---

## AT-009 — Manual Rollback

**Scenario:** Admin triggers rollback on a failed installation

**Steps:**
1. Navigate to a `failed` installation that has rollback checkpoints
2. Click "بازگشت" button
3. Confirm the JS `confirm()` dialog
4. Submit

**Expected:**
- [ ] Rollback button only visible when status=failed AND checkpoints exist
- [ ] Confirm dialog appears with clear warning message
- [ ] On confirm: redirects to detail with "عملیات بازگشت در صف قرار گرفت"
- [ ] `CustomerInstallationRollbackJob` dispatched to `customer-install` queue
- [ ] Installation status changes to `rolling_back`
- [ ] `CustomerInstallationRollbackService` (from PHASE-003A) handles execution

---

## AT-010 — Security Checks

**Scenario:** Admin verifies security controls

**Steps:**
1. Inspect HTML source of server list/edit pages
2. Inspect SSE stream payloads
3. Check browser Network tab for AJAX responses

**Expected:**
- [ ] No private keys or passwords in any HTML source
- [ ] No private keys or passwords in SSE event data
- [ ] No private keys or passwords in AJAX JSON responses
- [ ] CSRF token present in all form submissions
- [ ] All forms require authentication (unauthenticated access returns 401/302)

---

## AT-011 — Delete Server Guard

**Scenario:** Admin cannot delete a server with active installations

**Steps:**
1. Start an installation on a server (status=running)
2. Navigate to server list and attempt to delete that server

**Expected:**
- [ ] Delete redirects back with error: "این سرور در حال نصب فعال است. ابتدا نصب را متوقف کنید."
- [ ] Server NOT deleted from database

---

## AT-012 — Packages List

**Scenario:** Admin views registered packages

**Steps:**
1. Navigate to `/admin/infrastructure/customer-deployment/packages`

**Expected:**
- [ ] All packages listed with version, size, checksums
- [ ] Active package highlighted with green indicator and "فعال (production)" badge
- [ ] SHA256 copy button works (copies full hash to clipboard)
- [ ] Artisan command hint visible in footer

---

## Acceptance Sign-Off

| Test | Status | Tester | Date |
|---|---|---|---|
| AT-001 Dashboard | — | — | — |
| AT-002 Add Server | — | — | — |
| AT-003 Test Connection | — | — | — |
| AT-004 Edit Server | — | — | — |
| AT-005 Wizard | — | — | — |
| AT-006 SSE Monitor | — | — | — |
| AT-007 History | — | — | — |
| AT-008 Detail View | — | — | — |
| AT-009 Rollback | — | — | — |
| AT-010 Security | — | — | — |
| AT-011 Delete Guard | — | — | — |
| AT-012 Packages | — | — | — |

**Overall:** PENDING

---

*This document is part of the PHASE-003B Customer Installation Admin Center deliverables.*
