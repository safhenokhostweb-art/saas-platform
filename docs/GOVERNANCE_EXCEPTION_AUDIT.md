# GOVERNANCE EXCEPTION AUDIT

> Generated: 2026-06-29
> Status: AI_GOVERNANCE_EXCEPTION_POLICY_CERTIFIED
> Auditor: Claude (Lead Enterprise Architect)
> Scope: All governance enforcement layers

---

## 1. Canonical Exception Policy

Exactly 3 permitted cases where the AI may stop and ask the project owner.
No other exceptions exist or may be added.

### Exception 1 — Irreversible Destructive Operations
- `DROP DATABASE` on production
- `DROP TABLE` on production with live customer data
- `force-push` to main branch (destroys committed history)
- Deleting production backups
- Destroying the recovery repository
- Permanent data removal with no restore path

### Exception 2 — Business Decisions
- Pricing
- Commercial plans (monthly / annual / lifetime)
- Licensing policy (what the license permits or restricts)
- Customer-facing commercial options
- Contractual decisions with vendors or customers

### Exception 3 — Legal or Security Decisions
- Security policy changes
- Encryption policy (algorithms, key lengths, storage)
- Privacy policy changes
- Compliance decisions (GDPR, PCI-DSS, etc.)
- Certificate management (issuing, revoking, replacing)
- Key management (rotating, destroying, distributing master keys)

Everything else is fully autonomous: Analyze → Choose → Implement → Verify → Continue.

---

## 2. Current State (Pre-Audit)

| File | Exception 1 (Destructive) | Exception 2 (Business) | Exception 3 (Legal/Security) |
|------|--------------------------|------------------------|------------------------------|
| `CLAUDE.md` | DROP TABLE + force-push only | **ABSENT** | **ABSENT** |
| `HANDOVER.md` | DROP TABLE + force-push only | "business direction change" (vague) | "legal/security issue" (vague) |
| `DECISIONS.md` ADR-017 | DROP TABLE + force-push only | pricing, commercial policy, feature selection | "requiring sign-off" (no examples) |
| `AI_GOVERNANCE_POLICY.md` | DROP TABLE + force-push only | pricing, tier, commercial partnership | "violate law / security breach" (no examples) |
| `AI_DECISION_POLICY.md` | DROP TABLE + force-push + delete backups | pricing, features, third-party service | mixed into Category C incorrectly |
| `settings.json` hook | DROP TABLE + force-push only | **ABSENT** | "legal/security" (vague) |

---

## 3. Missing Items (Pre-Correction)

### Exception 1 — Missing from all files:
- `DROP DATABASE`
- Destroy recovery repository
- Permanent data removal

### Exception 1 — Missing from most files:
- Delete production backups (only in AI_DECISION_POLICY.md)

### Exception 2 — Missing from all files:
- Licensing policy
- Contractual decisions

### Exception 3 — Missing from all files:
- Encryption policy
- Privacy policy
- Compliance (GDPR, PCI-DSS)
- Certificate management
- Key management

### Structural issue:
- `AI_DECISION_POLICY.md` had "Regulatory or legal compliance strategy" in Category C (business) — should be Exception 3 (legal/security). Fixed by adding explicit Category E.

---

## 4. Corrections Applied

### `CLAUDE.md` — Rule Zero exception line
**Before:** `- تنها استثنا: \`DROP TABLE\` در production یا \`force-push\` روی main — فقط اینها را اعلام کن.`

**After:** Expanded to 3 numbered items with full Persian-language examples for all 3 exception categories. No existing rules removed.

### `docs/project-memory/HANDOVER.md` — Permitted stops line
**Before:** `- Permitted stops: (1) business direction change, (2) \`DROP TABLE\` / \`force-push\` on production, (3) legal/security issue`

**After:** All 3 expanded with canonical examples inline. No existing rules removed.

### `docs/project-memory/DECISIONS.md` — ADR-017 exceptions
**Before:** 3 items with narrow examples (DROP TABLE + force-push only; pricing + commercial only; no examples for legal/security)

**After:** All 3 updated with full canonical example lists. No existing rules removed.

### `docs/AI_GOVERNANCE_POLICY.md` — Section 5
**Before:** 5.1 (2 business examples), 5.2 (2 destructive examples), 5.3 (no examples)

**After:** 5.1 expanded with licensing + contractual; 5.2 expanded with DROP DATABASE + delete backups + destroy recovery + permanent data removal; 5.3 expanded with all 6 legal/security examples. No existing rules removed.

### `docs/AI_DECISION_POLICY.md` — Categories C and D
**Before:** Category C missing licensing/contractual; Category D missing DROP DATABASE/destroy recovery/permanent data removal; legal/security incorrectly in Category C

**After:** Category C complete; Category D complete; Category E added for legal/security decisions as separate explicit block. No existing rules removed.

### `.claude/settings.json` — PreToolUse hook message
**Before:** "Permitted stops: DROP TABLE production, force-push main, legal/security issue only."

**After:** All 3 exception categories listed with examples. Hook logic unchanged (exit 1 still blocks AskUserQuestion).

---

## 5. Final Consistency Verification

| Layer | Exception 1 | Exception 2 | Exception 3 | Hook Active |
|-------|-------------|-------------|-------------|-------------|
| `CLAUDE.md` | ✅ Full | ✅ Full | ✅ Full | N/A |
| `HANDOVER.md` | ✅ Full | ✅ Full | ✅ Full | N/A |
| `DECISIONS.md` ADR-017 | ✅ Full | ✅ Full | ✅ Full | N/A |
| `AI_GOVERNANCE_POLICY.md` | ✅ Full | ✅ Full | ✅ Full | N/A |
| `AI_DECISION_POLICY.md` | ✅ Full | ✅ Full | ✅ Full | N/A |
| `settings.json` hook msg | ✅ Full | ✅ Full | ✅ Full | ✅ PreToolUse / exit 1 |

---

## 6. What Was NOT Changed

- The `AskUserQuestion` hook mechanism (still blocks with exit 1)
- `"defaultMode": "bypassPermissions"` (unchanged)
- Any permission allow-list entries
- The autonomous execution flow: Analyze → Choose → Implement → Verify → Continue
- The prohibition on asking for implementation decisions
- The prohibition on asking "which option do you prefer?" or "can I continue?"
- Any existing ADR text except the exceptions list itself

---

## 7. Governance Enforcement Stack

```
CLAUDE.md Rule Zero          ← loaded at session start (runtime)
  + HANDOVER.md ADR-017      ← loaded at step 1 of recovery protocol
  + DECISIONS.md ADR-017     ← permanent architectural record
  + AI_GOVERNANCE_POLICY.md  ← authoritative full policy document
  + AI_DECISION_POLICY.md    ← decision framework with categories
  + settings.json hook       ← hard enforcement at tool execution layer
```

All 6 layers now carry identical, complete exception definitions.
No layer is weaker than another.
No exceptions exist outside these 3 categories.

---

**AI_GOVERNANCE_EXCEPTION_POLICY_CERTIFIED**
