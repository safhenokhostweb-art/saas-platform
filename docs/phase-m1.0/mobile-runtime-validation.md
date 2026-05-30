# Mobile Runtime Validation — Phase M1.0

## Validation Service

`MobileRuntimeValidationService::validate()` runs 10 dimensions and returns:

```php
[
    'score'   => 95,        // 0–100
    'grade'   => 'A',       // A+/A/A-/B+/B/B-/C/D/F
    'checks'  => [...],     // array of {name, status, message}
    'passed'  => 9,
    'warned'  => 1,
    'failed'  => 0,
    'total'   => 10,
]
```

## Grade Scale

| Score | Grade |
|---|---|
| 97–100 | A+ |
| 93–96 | A |
| 90–92 | A- |
| 87–89 | B+ |
| 83–86 | B |
| 80–82 | B- |
| 70–79 | C |
| 60–69 | D |
| < 60 | F |

## Artisan Commands

### `mobile:validate-runtime-governance`

Full 10-dimension governance validation.

```bash
php artisan mobile:validate-runtime-governance
php artisan mobile:validate-runtime-governance --strict           # warn→fail
php artisan mobile:validate-runtime-governance --fail-on-warning  # exit 1 on warn
php artisan mobile:validate-runtime-governance --json             # CI-friendly JSON
```

### `mobile:validate-realtime`

Validates Reverb config, Redis connectivity, presence, channel registry, authorization service.

```bash
php artisan mobile:validate-realtime
php artisan mobile:validate-realtime --json
```

### `mobile:validate-ota`

Validates version policy, update channels, bundle registry, enforcement service.

```bash
php artisan mobile:validate-ota
php artisan mobile:validate-ota --strict
```

### `mobile:validate-security`

Validates patch registry, tenant compliance, encryption key, FCM config, device trust policy.

```bash
php artisan mobile:validate-security --tenant=123
php artisan mobile:validate-security --json
```

### `mobile:runtime-status`

Live runtime status: registered tenants, realtime health, governance score.

```bash
php artisan mobile:runtime-status
php artisan mobile:runtime-status --tenant=123   # include tenant governance report
php artisan mobile:runtime-status --json
```

### `mobile:diagnostics`

Full diagnostic sweep: realtime, connections, crash report, delivery analytics.

```bash
php artisan mobile:diagnostics
php artisan mobile:diagnostics --tenant=123
php artisan mobile:diagnostics --full              # include crash report + analytics
php artisan mobile:diagnostics --json
```

## CI Integration

Add to your CI pipeline:

```yaml
- name: Validate Mobile Runtime
  run: |
    php artisan mobile:validate-runtime-governance --json --fail-on-warning
    php artisan mobile:validate-realtime --json
    php artisan mobile:validate-ota --json
    php artisan mobile:validate-security --json
```
