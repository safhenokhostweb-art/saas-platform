# Inheritance Rules

```text
Platform Master version
  └── Template reference version
        └── Site package version
              └── Customer variant version (when approved)
```

Each node records stable ID, exact version, parent/source reference, checksum, owner type/scope, creation reason, license snapshot, compatibility profile, and validation result.

A template alias never becomes the source of truth: it resolves to an exact Master version. A site variant references both its direct parent and original Master. Brand adaptations additionally retain customer/site ID, normalized theme fingerprint, generation pipeline version, private prompt/provider references, and approval.

Publishing a new Master or template version does not silently alter installed sites. An upgrade creates a new candidate site package. Rollback creates a new package version copied from a previous verified lock and never rewrites history.

Lineage cycles, moving/unversioned parents, missing checksums, ownership mismatch, and cross-site parents are invalid.

