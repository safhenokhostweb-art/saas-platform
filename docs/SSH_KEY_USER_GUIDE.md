# SSH Key User Guide

**Audience:** Platform administrators setting up deployment targets  
**Date:** 2026-06-28

---

## What Is an SSH Key?

SSH keys are a pair of cryptographic files that allow secure login to a server **without a password**.

| Key | Where It Lives | Safe to Share? |
|-----|----------------|----------------|
| Private Key | Only in this platform (encrypted) | NEVER |
| Public Key | On the target server | Yes — completely safe |

---

## Why Keys Are Better Than Passwords

| Aspect | Password | SSH Key |
|--------|----------|---------|
| Brute-force attack | Possible | Mathematically infeasible |
| Phishing | Stealable | Not useful without private key |
| Network sniffing | Risk on weak TLS | Never exposed in transit |
| Rotation | Must be changed periodically | Ed25519 keys are effectively permanent |
| Audit trail | Shared credentials | One key per purpose |

---

## How the Connection Works

```
┌─────────────────────────┐           ┌──────────────────────────┐
│   SaaS Platform         │           │   Your Server            │
│                         │           │                          │
│  Private Key            │  ──────▶  │  ~/.ssh/authorized_keys  │
│  (AES-256-GCM)          │  signs    │  (Public Key)            │
│                         │  ──────▶  │                          │
│                         │  verifies │  Grants Access ✓         │
└─────────────────────────┘           └──────────────────────────┘

No password is ever sent over the network.
```

---

## Generating a New SSH Key

1. Open **Deployment Targets** → **Add Target** → select **VPS / SSH**
2. Under Authentication, choose **"تولید کلید جدید Ed25519"**
3. Click **"ایجاد کلید Ed25519"** — the key pair is generated server-side
4. The **public key** appears in a green terminal box
5. Click **"کپی کردن"** to copy the public key
6. Install the public key on your server (see below)
7. Check the confirmation box, then save the form

The private key is automatically placed in the hidden form field and will be AES-256-GCM encrypted on save.

---

## Installing the Public Key on Your Server

**Option A — Manual (recommended for first-time setup):**

```bash
# 1. Connect to your server as root (or the deployment user)
ssh root@<your-server-ip>

# 2. Ensure the .ssh directory exists
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# 3. Add the public key to authorized_keys
echo 'PASTE_PUBLIC_KEY_HERE' >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

**Option B — Quick one-liner:**

```bash
echo 'ssh-ed25519 AAAA...xyz saas-platform-2026-06-28' >> ~/.ssh/authorized_keys
```

**Option C — Using ssh-copy-id:**

```bash
# On your local machine (requires temporary password access)
ssh-copy-id -i /path/to/public_key.pub root@<your-server-ip>
```

---

## Using an Existing Key

If you already have a private key file (`id_ed25519` or `id_rsa`):

1. Open the key file in a text editor
2. Copy everything including `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----`
3. Paste into the **"کلید خصوصی SSH"** textarea in the form
4. Make sure the corresponding public key is already in `~/.ssh/authorized_keys` on the server

---

## Supported Key Types

| Type | Algorithm | Recommended |
|------|-----------|-------------|
| Ed25519 | Elliptic Curve | ✔ Yes — modern, fast, short |
| RSA-4096 | RSA | ✔ Compatible — use if Ed25519 not supported |
| RSA-2048 | RSA | ⚠ Minimum — consider upgrading |
| DSA / ECDSA | Legacy | ✗ Not supported |

---

## Key Security in This Platform

- Private keys are stored with **AES-256-GCM** encryption using a unique 12-byte nonce per key
- The master encryption key (`INSTALLER_MASTER_KEY`) is stored only in the server `.env`
- Private keys are **never displayed** after saving — only the fingerprint is shown
- Keys can be rotated without downtime via the Authentication Center

---

## Troubleshooting

**"Permission denied (publickey)"**
- Confirm the public key is in `~/.ssh/authorized_keys` on the server
- Check permissions: `chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys`
- Ensure `PubkeyAuthentication yes` in `/etc/ssh/sshd_config`

**"Connection refused"**
- Verify the server IP and port
- Check if SSH service is running: `systemctl status sshd`
- Check firewall: `ufw status` or `iptables -L`

**"Host key verification failed"**
- The server's host key changed (possible MITM or server rebuild)
- Use the Authentication Center to update the stored fingerprint

---

## Key Rotation

Keys should be rotated periodically or after:
- A team member with access leaves
- Any suspected compromise
- Server rebuild with new host keys

Use **Authentication Center** → **SSH Keys** → **Rotate Key** to rotate without downtime.
