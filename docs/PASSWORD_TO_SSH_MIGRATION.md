# Password to SSH Key Migration Guide

**Audience:** Platform administrators migrating SSH deployment targets from password to key authentication  
**Date:** 2026-06-28

---

## Why Migrate?

| Reason | Details |
|--------|---------|
| Security | Passwords can be brute-forced; Ed25519 keys cannot |
| No expiry | Keys don't expire; passwords must be rotated |
| No phishing | Keys can't be phished |
| Automation | Keys allow non-interactive deployment without stored passwords |
| Compliance | Many security standards require key-based SSH |

Migration is **never forced** — password authentication continues to work.

---

## Migration Wizard (Built-in)

When a deployment target uses SSH password authentication, the edit form shows a migration banner:

```
┌──────────────────────────────────────────────────────────────┐
│ 🔑 مهاجرت به کلید SSH پیشنهاد می‌شود                        │
│ این سرور از رمز عبور SSH استفاده می‌کند.                     │
│                                    [ مهاجرت به کلید SSH ]   │
└──────────────────────────────────────────────────────────────┘
```

Clicking the button opens a **3-step in-page wizard**:

### Step 1 — Generate Key
- Click "تولید کلید Ed25519"
- An Ed25519 key pair is generated
- The private key is loaded into the form automatically

### Step 2 — Install Public Key
- The public key is displayed
- Copy it to the server's `~/.ssh/authorized_keys`

### Step 3 — Save & Test
- Save the form — the target now uses the new key
- Use the "تست اتصال" button to verify the connection works

---

## Manual Migration Steps

If you prefer to migrate without the wizard:

### 1. Generate a Key Pair

**Option A — In-platform (recommended):**
- Go to **Authentication Center** → **SSH Keys** → **Generate New Key**
- Select Ed25519, give it a label
- Download or copy the public key

**Option B — On your local machine:**
```bash
ssh-keygen -t ed25519 -C "saas-platform-$(date +%Y-%m-%d)" -f ~/.ssh/saas_platform_key
```

### 2. Install the Public Key on the Server

```bash
# Connect with current password
ssh -p <port> <username>@<server-ip>

# Add the public key
echo '<public-key-content>' >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### 3. Test Key-Based Login

Before disabling password auth, **test the key works**:

```bash
ssh -i ~/.ssh/saas_platform_key -p <port> <username>@<server-ip>
```

If you get a shell prompt, the key works.

### 4. Update the Deployment Target

- Go to **Deployment Targets** → **Edit Target**
- Switch Authentication to **"کلید خصوصی SSH"**
- Paste the private key content into the textarea
- Save

### 5. Test Connection in Platform

Use the **"تست اتصال"** button on the edit page to confirm the platform can connect with the new key.

### 6. (Optional) Disable Password Authentication

Only after confirming key-based login works:

```bash
# Edit SSH config on the server
nano /etc/ssh/sshd_config

# Set:
PasswordAuthentication no
PubkeyAuthentication yes

# Restart SSH (keep current session open!)
systemctl restart sshd

# Test from a NEW terminal before closing the current session
ssh -i ~/.ssh/saas_platform_key -p <port> <username>@<server-ip>
```

**Warning:** Do NOT disable password auth until you have confirmed key-based access works. Keep an active SSH session open as a safety net.

---

## Rollback

If something goes wrong after migration:

1. Use the existing SSH session (do not close it)
2. Re-enable password auth: `PasswordAuthentication yes` in `/etc/ssh/sshd_config`
3. Restart SSH: `systemctl restart sshd`
4. Update the deployment target back to password auth

---

## Checklist

- [ ] New Ed25519 key pair generated
- [ ] Public key added to `~/.ssh/authorized_keys` on server
- [ ] Key-based login tested manually from terminal
- [ ] Deployment target updated in platform with private key
- [ ] Platform "تست اتصال" returns success
- [ ] (Optional) Password authentication disabled on server
- [ ] (Optional) Old password credential removed or rotated

---

## Security Notes

- The private key is stored encrypted (AES-256-GCM) in the platform database
- The master encryption key is in `/var/www/saas-platform/.env` as `INSTALLER_MASTER_KEY`
- Private keys are never displayed in the admin UI after saving — only the SHA-256 fingerprint
- Keys can be rotated again in the future via Authentication Center → SSH Keys → Rotate
