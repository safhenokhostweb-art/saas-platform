# Trusted Publisher Model — Phase 8.6

## Trust Levels (ModuleTrustRegistry)

| Level | Description | Can Execute |
|---|---|---|
| core | Platform core module | yes |
| trusted | Verified + trusted publisher + valid hash | yes |
| sandboxed | Valid hash, untrusted publisher | yes (sandbox only) |
| untrusted | Verification failed | no |

## Publisher Registry

Publishers are stored in cache (`plugin.trust.publishers`) with 24h TTL.
Add publishers via `TrustedPublisherRegistry.trust(publisherId, name, addedBy)`.

## Verification Flow

```
ExtensionAuthenticityService.authenticate(moduleId, publisherId, modulePath, declaredHash)
  1. PluginSignatureVerifier.verify()
     a. TrustedPublisherRegistry.isTrusted(publisherId)
     b. PluginIntegrityValidator.verifyHash(modulePath, declaredHash)
  2. publisher_trusted + hash_valid → TRUSTED
  3. hash_valid only → SANDBOXED
  4. neither → UNTRUSTED (execution blocked)
```

## Integrity Hashing

`PluginIntegrityValidator` computes SHA-256 over all PHP files in sorted order using `hash_init / hash_update_file / hash_final`. This is deterministic and tamper-evident.

## Trust Score (ExtensionTrustScore)

| Condition | Score |
|---|---|
| CORE | 100 |
| TRUSTED | 85 |
| SANDBOXED | 50 |
| UNTRUSTED | 0 |
| Untrusted publisher penalty | ×0.7 |
| WARNING deps | -10 |
| UNSAFE deps | -30 |
| BLOCKED deps | -100 |
