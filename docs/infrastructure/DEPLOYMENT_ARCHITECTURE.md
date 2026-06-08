# Future Deployment Architecture
> Design document — No remote execution implemented. Configuration-only changes.
> Created: 2026-06-08

---

## Scenario A — Single VPS (Current / Simplest)

```
┌─────────────────────────────────────────────┐
│                  VPS Server                  │
│                                              │
│  ┌────────────┐  ┌────────────┐  ┌────────┐ │
│  │  Laravel   │  │   MySQL    │  │ Redis  │ │
│  │ (PHP-FPM + │  │  (local)   │  │(local) │ │
│  │  Nginx)    │  └────────────┘  └────────┘ │
│  └────────────┘                              │
│  ┌───────────────────────────────────────┐  │
│  │     Local Storage (storage/app/)      │  │
│  └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘

ENV required:
  APP_URL=https://saas.yourdomain.com
  DB_HOST=127.0.0.1
  REDIS_HOST=127.0.0.1
  FILESYSTEM_DISK=local
```

**Configuration change:** Update `.env` with production values. Zero code changes.

---

## Scenario B — App + Database Separated

```
┌──────────────────────┐     ┌──────────────────────┐
│   Application Server  │     │   Database Server     │
│                       │     │                       │
│  Laravel (PHP-FPM)   │────▶│  MySQL 8.x            │
│  Nginx               │     │  Redis                │
│  Redis (cache/queue)  │     │  (dedicated port 3306/6379)│
│  Storage: Local/S3   │     └──────────────────────┘
└──────────────────────┘

ENV changes:
  DB_HOST=10.0.0.2            ← internal network IP of DB server
  DB_PORT=3306
  REDIS_HOST=10.0.0.2         ← or separate Redis server
  REDIS_PORT=6379
```

**Configuration change:** Update 2 env vars. Zero code changes — already config-driven.

---

## Scenario C — App + Separate DB + Object Storage

```
┌──────────────┐     ┌──────────────┐     ┌──────────────────────┐
│  App Server  │────▶│  DB Server   │     │   Object Storage     │
│              │     │  MySQL 8.x   │     │   MinIO / S3          │
│  Laravel     │     │  Redis       │     │                      │
│  Nginx       │     └──────────────┘     │  Buckets:            │
│              │                          │  - uploads           │
│              │◀────────────────────────▶│  - exports           │
└──────────────┘                          │  - backups           │
                                          └──────────────────────┘

ENV changes:
  DB_HOST=db.internal
  FILESYSTEM_DISK=s3           ← or 'minio'
  AWS_ACCESS_KEY_ID=...
  AWS_SECRET_ACCESS_KEY=...
  AWS_DEFAULT_REGION=us-east-1
  AWS_BUCKET=saas-uploads
  AWS_ENDPOINT=https://minio.yourdomain.com   ← MinIO
  AWS_USE_PATH_STYLE_ENDPOINT=true             ← MinIO only
  UPLOAD_DISK=s3               ← after INFRA-2 remediation
  EXPORT_DISK=s3
```

**Code required:** Complete INFRA-2 remediation (Storage facade migration).

---

## Scenario D — Multiple Customer Sites on Different Servers

```
                     ┌──────────────────────────┐
                     │   Central SaaS Admin      │
                     │   (saas-platform)         │
                     │   - License management    │
                     │   - Tenant provisioning   │
                     │   - Education module      │
                     └────────────┬─────────────┘
                                  │  API
              ┌───────────────────┼───────────────────┐
              ▼                   ▼                   ▼
   ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
   │  Tenant VPS A   │  │  Tenant VPS B   │  │  Tenant VPS C   │
   │  customer-site  │  │  customer-site  │  │  customer-site  │
   │  site_id=1      │  │  site_id=2      │  │  site_id=3      │
   └─────────────────┘  └─────────────────┘  └─────────────────┘

Domain routing:
  client1.com ──▶ VPS A (IP: x.x.x.x)
  client2.com ──▶ VPS B (IP: y.y.y.y)
  client3.com ──▶ VPS C (IP: z.z.z.z)

No code change needed. Tenant domain resolution is already DB-driven.
Each customer-site deployment uses its own DB_HOST env var.
```

---

## Scenario E — Central SaaS Admin + Remote Tenant Management

```
┌─────────────────────────────────────────────────────────┐
│              Central SaaS Admin Platform                 │
│              (saas-platform)                             │
│                                                         │
│  ┌─────────────────┐   ┌──────────────────────────┐    │
│  │  Admin Panel     │   │  Infrastructure Module   │    │
│  │  - Users         │   │  - Servers registry      │    │
│  │  - Sites         │   │  - Domains inventory     │    │
│  │  - Licenses      │   │  - DB/Storage monitoring │    │
│  │  - Education     │   │  - Backup status         │    │
│  └─────────────────┘   └──────────────────────────┘    │
│                                                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │  (Future) Remote Agent Layer                      │  │
│  │  POST /api/remote/deploy     — NOT YET BUILT      │  │
│  │  POST /api/remote/backup     — NOT YET BUILT      │  │
│  │  GET  /api/remote/health     — NOT YET BUILT      │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
          │                    │                   │
          ▼                    ▼                   ▼
  ┌─────────────┐     ┌─────────────┐    ┌─────────────┐
  │  Tenant A    │     │  Tenant B   │    │  Tenant C   │
  │  VPS / Cloud │     │  VPS        │    │  Shared     │
  └─────────────┘     └─────────────┘    └─────────────┘

Remote agent design (NOT IMPLEMENTED — design only):
- Each tenant server runs a lightweight Agent service (small Laravel app or daemon)
- Agent exposes authenticated REST endpoints over HTTPS + HMAC signing
- Central platform POSTs commands to Agent
- Agent reports health back to central platform
- No SSH. No shared credentials. Agent-pull model preferred.

Authentication: 
  AGENT_SECRET_KEY env per tenant server
  HMAC-SHA256 signature on each request
  IP allowlist for central platform
```

---

## SMS Infrastructure Architecture

```
┌───────────────────────────────────────────┐
│           SmsGatewayResolver               │
│                                           │
│  ┌─────────────────────────────────────┐  │
│  │         FailoverSmsGateway          │  │
│  │                                     │  │
│  │  Primary: SMS_PROVIDER              │  │
│  │    └── MeliPayamakGateway  ─────┐   │  │
│  │                                 │   │  │
│  │  Failover: SMS_FAILOVER_PROVIDER │   │  │
│  │    └── KavenegarGateway    ─────┤   │  │
│  │    └── GhasedakGateway    ──────┘   │  │
│  │                                     │  │
│  │  Health tracking: Cache             │  │
│  │  sms_health_primary                 │  │
│  │  sms_health_secondary               │  │
│  └─────────────────────────────────────┘  │
└───────────────────────────────────────────┘

ENV for production:
  SMS_PROVIDER=meli_payamak
  SMS_FAILOVER_PROVIDER=kavenegar
  SMS_MELI_USERNAME=...
  SMS_MELI_PASSWORD=...
  SMS_MELI_FROM=...
  SMS_KAVENEGAR_API_KEY=...
  SMS_KAVENEGAR_SENDER=...
```

---

## Backup Strategy Design

### Database Backup
```
Schedule: Daily at 02:00 via Laravel Scheduler
Command:  php artisan backup:database
Storage:  off-server S3 / MinIO bucket (BACKUP_DISK env)
Retention: 7 daily, 4 weekly, 3 monthly
Encryption: AES-256 with BACKUP_ENCRYPTION_KEY

Table: infra_database_servers tracks last_backup_at
Alert: if last_backup_at > 25 hours → Slack webhook
```

### File Storage Backup
```
Schedule: Daily at 03:00
Source:   storage/app/public/ (or S3 primary bucket)
Target:   separate backup bucket / off-server
Strategy: Incremental with rsync or S3 sync --delete

Table: infra_storage_providers tracks used_gb for growth monitoring
```

### Tenant Backup
```
Per-tenant: separate backup on site-specific schedule
Managed:    via tenant's own hosting subscription
Central:    Central platform stores backup_at timestamp in infra_servers
```

### Off-Server Storage
```
Current: Local filesystem only
Future:  Set BACKUP_DISK=s3 to enable off-server backups
Config:  config/filesystems.php 's3' disk already configured
```

---

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Deploy with wrong APP_URL | High | Medium | Pre-deploy checklist, fail on APP_URL=localhost |
| S3 migration breaks uploads | Medium | High | Complete INFRA-2 before switching FILESYSTEM_DISK |
| SMS primary failure (no failover) | Medium | High | **Mitigated** — FailoverSmsGateway deployed |
| DB connection to wrong server | Low | Critical | Explicit DB_HOST required in production checklist |
| Tenant data cross-contamination | Very Low | Critical | site_id isolation structural — unchanged |
| CORS blocking tenant API calls | Was High | High | **Fixed** — CORS now env-driven |
| Storage path assumptions on Windows | Medium | Medium | Raw paths use `storage_path()` which is OS-agnostic |

---

## Implementation Roadmap

| Phase | Priority | Effort | What |
|-------|----------|--------|------|
| INFRA-1 | Critical | 2h | Production `.env` setup, run migrations |
| INFRA-2 | High | 1d | Storage facade migration (F2.1, F2.3–F2.6) |
| INFRA-3 | Medium | 4h | Hardening (F2.2, F6.1, Sanctum domains) |
| INFRA-4 | Medium | 1d | DB backup command + scheduler |
| INFRA-5 | Low | 2d | SMS delivery statistics dashboard |
| INFRA-6 | Future | TBD | Remote Agent Layer (Scenario E) |
