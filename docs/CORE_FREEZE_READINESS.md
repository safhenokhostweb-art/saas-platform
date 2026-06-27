# CORE FREEZE READINESS ASSESSMENT
> CORE_CERTIFICATION_PHASE_1 — 2026-06-27
> Question: Can the core infrastructure be frozen (no further changes) and declared production-stable?

---

## Freeze Criteria

A subsystem is **FREEZE-READY** when:
- No CRITICAL gaps exist
- All HIGH gaps have documented mitigations or accepted risks
- No correctness bugs (not just performance)
- Recovery paths are functional

A subsystem is **NOT FREEZE-READY** when:
- A CRITICAL gap exists that can cause data loss, duplicate execution, or unrecoverable state
- A core feature is documented but not implemented

---

## Freeze Status by Subsystem

### ✅ FREEZE-READY Subsystems

#### 1. Installer Foundation — FREEZE-READY
- All five baseline tasks completed and verified
- SHA-256 manifest correct
- Immutability enforced
- Admin panel read-only
- Remaining gaps (W-INS-01 atomic lock, W-INS-03 manifest in REQUIRED_FILES) are LOW severity with no data-loss path

#### 4. Disaster Recovery Center — FREEZE-READY
- Scoring, alerting, and integrity checks are functional
- Gaps are measurement accuracy issues (biased scoring for fresh installs, 5-min cache lag)
- No correctness bugs that would cause false PASS on genuine disaster

#### 5. Tenant Isolation — FREEZE-READY (with caveat)
- Structural isolation is sound (site_id everywhere, global scope)
- Restore isolation gap (W-ISO-02) is shared with Restore Foundation blocking item
- If Restore Foundation is fixed, Tenant Isolation becomes fully freeze-ready

#### 6. License System — FREEZE-READY
- HMAC-SHA256 is cryptographically sound
- Lazy rehash works correctly
- Domain validation and expiry enforced
- Only gap: rate limiting on validate endpoint (30-min fix)

#### 8. Scheduler — FREEZE-READY (with caveat)
- Schedule definitions are correct
- withoutOverlapping() on all critical commands
- SCHEDULER_DEAD false-positive (W-SCH-01) must be fixed but doesn't affect scheduling correctness

#### 9. Finance Core — FREEZE-READY
- No critical vulnerabilities
- idempotent auto-capture via unique constraint
- Gaps are structural (manual entry no idempotency, query efficiency) — non-blocking

#### 10. Security — FREEZE-READY (with caveat)
- No exploitable vulnerabilities found
- Password-in-CLI and rate-limiting gaps must be addressed, but platform is not unusably insecure
- HMAC license hashing is solid

#### 11. Performance — FREEZE-READY
- No blocking performance issues at current scale
- N+1 Arvan calls and finance query patterns will need optimization at scale
- Current tenant/backup volume doesn't trigger problems yet

---

### ❌ NOT FREEZE-READY Subsystems

#### 2. Backup Engine — NOT FREEZE-READY
**Blocker:** W-BAK-01 — Arvan accumulates backup files indefinitely. No retention pruning. At 1 backup/day × average 500MB compressed = ~15GB/month. After 6 months = 90GB of unbounded Arvan storage cost. This is a time-bomb, not a future concern.

**Blocker:** W-BAK-02 — No tenant database backup. The "tenant restore" feature advertised on the DR Center UI requires tenant backups that are never created. This is a correctness gap in the documented feature set.

#### 3. Restore Foundation — NOT FREEZE-READY
**Blocker:** W-RST-01 — `executePlatformDatabaseRestore()` is not implemented. The platform DB restore dry-run describes 12 steps but the execution method does not exist. If the production database is corrupted, there is NO automated restore path.

**Blocker:** W-RST-02 — `deleteTenantData()` + `copyTenantData()` without transaction. A server crash between deletion and copy leaves tenant data permanently inconsistent. This is a data-loss path.

**Blocker:** W-RST-05 — `retry_after=90` will trigger duplicate restore execution for any database larger than what can be downloaded+imported in 90 seconds.

#### 7. Queue System — NOT FREEZE-READY
**Blocker:** W-QUE-01 — Default queue connection is `sync`. If the `QUEUE_CONNECTION` env var is missing or incorrect on production, restore jobs run synchronously and fail after PHP timeout. This is a silent catastrophic failure mode with no error surfacing.

**Blocker:** W-QUE-02 — `retry_after=90` (same as SCA-001 above) — correctness issue, not performance.

**Blocker:** W-QUE-03 — Supervisor group name mismatch means queue workers are NOT restarted on deployment. Workers run stale code after every deploy.

#### 12. Scalability — NOT FREEZE-READY
**Blocker:** SCA-001 — The 90s retry_after is a correctness bug for restore operations. This is architectural, not just a performance concern.

**Blocker:** SCA-002 — Default sync connection (same as W-QUE-01).

---

## Prioritized Bug-Fix Roadmap

### P0 — Must Fix Before Any Production Restore

| ID | Fix | Effort | Impact |
|----|-----|--------|--------|
| FIX-QUE-01 | Set `QUEUE_CONNECTION=redis` in production + add installer health check | 30min | Prevents sync-mode catastrophic failure |
| FIX-QUE-03 | Fix deploy.sh Supervisor group name `saas-worker` → `saas-platform-worker` | 15min | Workers restart on deploy |
| FIX-QUE-02 | Add `restore` connection with `retry_after=7200` | 1h | Prevents duplicate restore execution |
| FIX-SEC-01 | Replace `-p$password` with `MYSQL_PWD=` env var | 2h | Passwords not visible in ps aux |

### P1 — Must Fix Before Core Freeze

| ID | Fix | Effort | Impact |
|----|-----|--------|--------|
| FIX-RST-01 | Implement `executePlatformDatabaseRestore()` + `RestorePlatformDatabaseJob` | 8h | Platform DB restore functional |
| FIX-RST-02 | Wrap deleteTenantData + copyTenantData in DB transaction | 2h | Tenant restore atomic |
| FIX-BAK-01 | Implement Arvan retention pruning (keep last N per prefix) | 3h | Unbounded storage cost eliminated |
| FIX-BAK-02 | Implement tenant database backup command + add to Kernel.php | 4h | Tenant restore has source data |
| FIX-SCH-01 | Fix SCHEDULER_DEAD false-positive (25h window or heartbeat approach) | 2h | Eliminates daily false CRITICAL alerts |

### P2 — Should Fix Before Scale

| ID | Fix | Effort | Impact |
|----|-----|--------|--------|
| FIX-SEC-02 | Add throttle:60,1 to license validate route | 30min | Rate limit protection |
| FIX-PER-01 | Batch Arvan metadata in listFiles() (single API call vs N calls) | 3h | Eliminates N+1 Arvan HTTP bottleneck |
| FIX-PER-02 | Add composite index on infra_backup_runs | 30min | Query performance at scale |
| FIX-FIN-01 | Add amount max validation in fromCustomerSitePush | 30min | API abuse prevention |
| FIX-DR-01 | Fix evalRestoreLogs() fresh-install bias | 1h | Accurate DR score for new installs |
| FIX-DR-02 | Fix ALERT-007 lock-stuck TTL math | 30min | Correct restore lock alert timing |
| FIX-SCA-02 | Queue priority separation (restore > backup > default > ai) | 2h | Resource isolation |

### P3 — Technical Debt (No Freeze Impact)

| ID | Fix | Effort |
|----|-----|--------|
| FIX-INS-01 | Add manifest.json to REQUIRED_FILES check | 30min |
| FIX-INS-02 | Atomic flock() on install.lock write | 1h |
| FIX-PER-03 | Share Arvan cache between DrReadinessScoreService + DrIntegrityService | 1h |
| FIX-FIN-02 | Refactor kpis() into single aggregated query | 2h |

---

## Total Effort to Reach FREEZE-READY

| Priority | Total Effort | Deadline Recommendation |
|----------|-------------|------------------------|
| P0 (blocking) | ~3.5h | Before next production restore attempt |
| P1 (freeze-blocking) | ~19h | Before declaring Core Frozen |
| P2 (scale-ready) | ~8h | Before > 50 tenants |
| P3 (debt) | ~4.5h | Any sprint |

**Total to FREEZE-READY: ~22.5 hours of implementation**

---

## Final Verdict

```
CORE_NOT_READY_FOR_FREEZE

Reason: 4 subsystems have blocking gaps:
  - Backup Engine: Arvan retention unbounded + no tenant backup
  - Restore Foundation: Platform DB restore not implemented + no transaction on tenant restore  
  - Queue System: sync default + retry_after=90 + Supervisor name mismatch
  - Scalability: retry_after correctness bug

Subsystems that ARE freeze-ready: Installer, DR Center, Tenant Isolation, License, 
  Scheduler, Finance Core, Security, Performance (8/12 subsystems)

Overall Core Health: 73/100

Path to FREEZE-READY: Complete P0 fixes (3.5h) + P1 fixes (19h) = ~22.5h total effort
```
