# Deployment Target Driver UI Reference

**Phase:** PHASE-003D.5  
**Status:** Complete  
**Date:** 2026-06-28

---

## Driver Metadata Map

All driver UI behavior is declared in `CustomerRemoteServerAdminController::driverMetaMap()`.
This map is the authoritative source — views never contain driver-specific hardcoded logic.

### Map Structure

```php
[
    'driver_key' => [
        'label'        => string,   // Full Persian label
        'label_short'  => string,   // Short label for table badges
        'description'  => string,   // Shown in create form type selector
        'color'        => string,   // Tailwind color prefix (indigo/blue/orange/teal/cyan)
        'implemented'  => bool,     // true = driver PHP class is functional
        'auth_types'   => array,    // Supported auth methods: key => label
        'default_port' => int,      // Pre-filled in port field
        'fields'       => array,    // Which form fields to show
        'capabilities' => array,    // 9 capability flags (bool)
    ],
]
```

---

## Per-Driver Specifications

### SSH (VPS / Dedicated Server)
- **Color:** Indigo
- **Implemented:** Yes (SshDeploymentDriver)
- **Auth:** Key (RSA/ED25519) or Password
- **Port:** 22
- **Credential storage:** `private_key_encrypted` or `password_encrypted`
- **All capabilities enabled**

### DirectAdmin
- **Color:** Blue
- **Implemented:** No (stub — DirectAdminDeploymentDriver)
- **Auth:** API Key (login:apitoken format)
- **Port:** 2222
- **Credential storage:** `api_token_encrypted`
- **Capabilities:** `ssl`, `database`, `cron` only

### cPanel
- **Color:** Orange
- **Implemented:** No (stub — CpanelDeploymentDriver)
- **Auth:** API Token (UAPI/cPanel API2)
- **Port:** 2083
- **Credential storage:** `api_token_encrypted`
- **Capabilities:** `ssl`, `database`, `cron` only

### SFTP (Pure)
- **Color:** Teal
- **Implemented:** No (stub — SftpDeploymentDriver)
- **Auth:** Username + Password
- **Port:** 22
- **Credential storage:** `password_encrypted`
- **Capabilities:** none (file transfer only)
- **Note:** execute() throws BadMethodCallException by design

### Docker Host
- **Color:** Cyan
- **Implemented:** No (stub — DockerDeploymentDriver)
- **Auth:** SSH Key or Password (via Docker's underlying SSH)
- **Port:** 22
- **Credential storage:** `private_key_encrypted` or `password_encrypted`
- **Capabilities:** `queue`, `redis`, `database`, `storage`, `cron`, `docker`, `reboot`

---

## Capability Labels (UI)

| Key | Persian Label |
|-----|--------------|
| queue | صف کار |
| supervisor | Supervisor |
| redis | Redis |
| ssl | SSL |
| database | ساخت DB |
| storage | Storage Link |
| cron | زمانبند |
| docker | Docker |
| reboot | ریبوت |

---

## Form Field Visibility Matrix

| Field | SSH | DA | cPanel | SFTP | Docker |
|-------|-----|----|--------|------|--------|
| name (label) | ✓ | ✓ | ✓ | ✓ | ✓ |
| host | ✓ | ✓ | ✓ | ✓ | ✓ |
| port | ✓ | ✓ | ✓ | ✓ | ✓ |
| username | ✓ | ✓ | ✓ | ✓ | ✓ |
| ssh auth radio | ✓ | — | — | — | ✓ |
| private_key textarea | ✓ | — | — | — | ✓ |
| ssh password | ✓ | — | — | — | ✓ |
| api_token | — | ✓ | ✓ | — | — |
| sftp password | — | — | — | ✓ | — |
| domain | — | ✓ | ✓ | ✓ | — |
| path | — | ✓ | ✓ | ✓ | — |

---

## Validation Rules

Defined in `CustomerRemoteServerAdminController::credentialRules()` and
`connectionRules()`:

- **SSH/Docker:** `auth_method` required; `private_key` required_if key; `password` required_if password
- **cPanel/DirectAdmin:** `api_token` required on create, optional on update
- **SFTP:** `password` required on create, optional on update
- **All:** `label` required max:100, `host` required max:255

---

## Stub Warning Behavior

When `$meta['implemented'] === false`:
- Create form: amber warning banner shown below type cards
- Edit form: amber warning banner shown at top
- Index table: "stub" gray badge appended to type chip
- Test button: disabled (gray, cursor-not-allowed) in index; hidden in edit
