# Deployment Guide — Phase M2.1

## Backend Deployment

### 1. Database Migration

The assignment audit table must exist:

```sql
CREATE TABLE IF NOT EXISTS assignment_audit (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id VARCHAR(36)   NOT NULL,
  action          VARCHAR(50)   NOT NULL,
  payload         JSON          NOT NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conversation (conversation_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. New Routes Registered

The following routes are added to `/api/mobile/*` in `mobile-api.php`:

- `GET /api/mobile/inbox` — tenant inbox list
- `GET /api/mobile/inbox/tenants` — assigned tenants
- `GET /api/mobile/inbox/summary` — inbox summary
- `GET /api/mobile/inbox/sla` — SLA counters
- `GET /api/mobile/hub` — unified conversation hub
- `POST /api/mobile/hub/bulk-read` — bulk mark read
- `GET /api/mobile/dashboard` — dashboard stats
- `POST /api/mobile/conversations/{id}/assign` — assign
- `POST /api/mobile/conversations/{id}/reassign` — reassign
- `POST /api/mobile/conversations/{id}/unassign` — unassign
- `POST /api/mobile/conversations/{id}/escalate` — escalate
- `GET /api/mobile/conversations/{id}/assignment` — current assignment
- `GET /api/mobile/conversations/{id}/assignment/history` — audit history
- `POST /api/mobile/conversations/{id}/presence/join` — join presence
- `POST /api/mobile/conversations/{id}/presence/leave` — leave presence

### 3. Service Registration

Register new services in your `AppServiceProvider`:

```php
$this->app->bind(
    \SaaSPlatform\CoreUtils\MobileRuntime\Inbox\TenantInboxService::class,
    fn ($app) => new TenantInboxService(
        $app->make(\SaaSPlatform\CoreUtils\MobileRuntime\Presence\PresenceRuntimeManager::class)
    )
);

$this->app->bind(
    \SaaSPlatform\CoreUtils\MobileRuntime\Typing\ConversationTypingService::class,
    fn ($app) => new ConversationTypingService(
        $app->make(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\TypingIndicatorRuntime::class)
    )
);

$this->app->bind(
    \SaaSPlatform\CoreUtils\MobileRuntime\Presence\ConversationPresenceService::class,
    fn ($app) => new ConversationPresenceService(
        $app->make(\SaaSPlatform\CoreUtils\MobileRuntime\Presence\PresenceRuntimeManager::class)
    )
);
```

### 4. Console Command Registration

Add to `Kernel.php` or a service provider:

```php
\SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidateProductionClientCommand::class,
```

### 5. Reverb + Redis Verification

```bash
php artisan mobile:validate-production-client --json
```

Score must be ≥ 95. Fix any failing dimensions before deploying.

## Flutter Client Deployment

### Android

1. Build release APK: `flutter build apk --release --split-per-abi`
2. Add AndroidManifest entries (see `android-runtime.md`)
3. Upload to OTA server: `PUT /api/admin/ota/releases/{channel}`
4. SHA-256 checksum of each APK must be stored in OTA manifest

### Windows

1. Build: `flutter build windows --release`
2. Package: `Compress-Archive -Path "build\windows\x64\runner\Release\*" -DestinationPath installer.zip`
3. Upload to OTA server, include SHA-256 in manifest
4. Users will be prompted to update on next launch

### Sound Assets

Place audio files at:
```
mobile/assets/sounds/message_received.mp3
mobile/assets/sounds/assigned.mp3
mobile/assets/sounds/escalation.mp3
```

Add to `pubspec.yaml`:
```yaml
flutter:
  assets:
    - assets/sounds/
    - assets/icons/
```

## Environment Variables

No new environment variables are required. All M2.1 features use existing:
- `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`
- `REDIS_HOST`, `REDIS_PASSWORD`
- `BROADCAST_DRIVER=reverb`
